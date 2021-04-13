<?php

namespace App\Http\Controllers;

use App\Appointment;
use App\Car;
use App\Workshop;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $date = $request->query('date');
        $workshop_id = $request->query('workshop_id');

        // By default, only return appointments from today onwards
        $current_datetime = Carbon::now();
        $current_date = $current_datetime->toDateString();
        $appointments = Appointment::whereDate('end_time', '>=', $current_date);

        // Unless specified date, returns appointment on that date only
        if ($request->has('date')) {
            $appointments = Appointment::whereDate('start_time', $date);
        }

        // Filter appointment by workshop
        if ($request->has('workshop_id')) {
            $appointments->where('workshop_id', $workshop_id);
        }

        return $appointments->paginate();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $car_id = $request->input('car_id');
        $workshop_id = $request->input('workshop_id');
        $start_time = $request->input('start_time');
        $end_time = $request->input('end_time');

        $validate = Validator::make($request->all(), [
            'car_id' => 'required',
            'workshop_id' => 'required',
            'start_time' => 'required|date_format:Y-m-d H:i:s',
            'end_time' => 'required|date_format:Y-m-d H:i:s',
        ]);

        if ($validate->fails()) {
            return response()->json($validate->messages(), 422);
        }

        $current_datetime = Carbon::now();
        $new_start_time = Carbon::parse($start_time);
        $new_end_time = Carbon::parse($end_time);

        // New appointment must be newer than current datetime
        if ($new_start_time->lessThan($current_datetime)) {
            return response()->json(['error' => 'Date or time is invalid. Please select newer date and time.']);
        }

        // Check if the slot is occupied, then disable to create appointment
        $appointments = Appointment::where('workshop_id', $workshop_id)->get();

        $booked = false;
        foreach ($appointments as $appointment) {
            $booked_start_time = Carbon::parse($appointment->start_time);
            $booked_end_time = Carbon::parse($appointment->end_time);

            if (
                ($new_start_time->betweenIncluded($booked_start_time, $booked_end_time) || $new_end_time->betweenIncluded($booked_start_time, $booked_end_time)) ||
                ($booked_start_time->betweenIncluded($new_start_time, $new_end_time) || $booked_end_time->betweenIncluded($new_start_time, $new_end_time))
            )
            {
                $booked = true;
            }
        }

        if ($booked) {
            return response()->json(['error' => 'Slot is already booked. Please select different date and time']);
        }

        // ToDo: Automatically calculate end time
        $appointment = new Appointment;
        $appointment->car_id = $car_id;
        $appointment->workshop_id = $workshop_id;
        $appointment->start_time = $start_time;
        $appointment->end_time = $end_time;
        $appointment->save();

        return $appointment;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Appointment  $appointment
     * @return \Illuminate\Http\Response
     */
    public function show(Appointment $appointment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Appointment  $appointment
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Appointment $appointment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Appointment  $appointment
     * @return \Illuminate\Http\Response
     */
    public function destroy(Appointment $appointment)
    {
        //
    }

    public function recommend(Request $request)
    {
        $car_id = $request->query('car_id'); // ToDo: Create filter options for this
        $distance_calculation = $request->query('distance_calculation'); // 'local', 'google'

        $local_calculation = true;
        if ($request->has('distance_calculation')) {
            if ($distance_calculation == 'google') {
                $local_calculation = false;
            }
        } else {
            if (!empty(config('app.google_api_key'))) {
                $local_calculation = false;
            }
        }

        // Get the lat & long from car id
        $car = Car::find($car_id);
        $lat1 = $car->latitude;
        $long1 = $car->longitude;

        $current_datetime = Carbon::now();
        $current_date = $current_datetime->toDateString();;
        $current_time = $current_datetime->toTimeString();;

        $existing_appointments = Appointment
            ::whereDate('end_time', '>=', $current_date)
//            ->whereTime('end_time', '>=', $current_time)
            ->get();

        $now = Carbon::now();
        $day_range = 5;
        $slot_interval = '1 hour';
        $result = [];

        $workshops = Workshop::all();
        foreach ($workshops as $i => $workshop) {
            $lat2 = $workshop->latitude;
            $long2 = $workshop->longitude;

            $opening_interval = CarbonInterval::createFromFormat('H:i:s', $workshop->opening_time);
            $closing_interval = CarbonInterval::createFromFormat('H:i:s', $workshop->closing_time);

            // If workshop is closed for today,
            if ($now->greaterThan(Carbon::parse($workshop->closing_time))) {
                $opening_datetime = Carbon::tomorrow()->add($opening_interval);
                $closing_datetime = Carbon::tomorrow()->add($closing_interval)->addDays($day_range);
            // Else if workshop still open, ToDo:// Get the next current hour
            } else {
                $opening_datetime = Carbon::today()->add($opening_interval);
                $closing_datetime = Carbon::today()->add($closing_interval)->addDays($day_range);
            }

            // Define slots using CarbonPeriod
            $slots = CarbonPeriod::create($opening_datetime, $slot_interval, $closing_datetime);

            // Remove slots outside operation hours & booked slots
            $available_slots = [];
            foreach ($slots as $slot) {
                $slot_time = CarbonInterval::createFromFormat('H:i:s', $slot->toTimeString()); // Convert slot to CarbonInterval for time comparison
                // If slots is within operating hours
                if ($slot_time->greaterThanOrEqualTo($opening_interval) && $slot_time->lessThan($closing_interval))
                {
                    // Checks if slots is booked
                    $booked = false;
                    foreach ($existing_appointments as $appointment) {
                        $start_time = Carbon::parse($appointment->start_time);
                        $end_time = Carbon::parse($appointment->end_time);
                        $slot_end = Carbon::parse($slot->toDateTimeString())->add($slot_interval);

                        if ($workshop->id == $appointment->workshop_id &&
                            (
                                ($start_time->greaterThanOrEqualTo($slot) && $start_time->lessThan($slot_end)) ||
                                ($end_time->greaterThan($slot) && $end_time->lessThan($slot_end))
                            ) ||
                            ($start_time->lessThanOrEqualTo($slot) && $end_time->greaterThanOrEqualTo($slot_end))
                        ) {
                            $booked = true;
                        }
                    }
                    if (!$booked) {
                        $available_slots[] = $slot->toDateTimeString();
                    }
                }
            }

            $result[$i] = [
                'workshop_id' => $workshop->id,
                'workshop_name' => $workshop->name,
                'distance' => $local_calculation ? $this->calculateDistance($lat1, $long1, $lat2, $long2) : $this->getDistance($lat1, $long1, $lat2, $long2),
                'available_slots' => $available_slots,
                ];

            // To get faster result, can try sort when appending the result
        }

        // Sort result by distance
        usort($result, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });


        return response()->json($result);
    }

    // Return distance in metres
    function calculateDistance($lat1, $long1, $lat2, $long2)
    {
        $lat1 = deg2rad($lat1);
        $long1 = deg2rad($long1);
        $lat2 = deg2rad($lat2);
        $long2 = deg2rad($long2);

        // Delta
        $dlati = $lat2 - $lat1;
        $dlong = $long2 - $long1;

        $val = pow(sin($dlati/2),2)+cos($lat1)*cos($lat2)*pow(sin($dlong/2),2);
        $res = 2 * asin(sqrt($val));

        $earth_radius = 6371000;

        return ($res*$earth_radius);
    }

    // Return distance in metres
    function getDistance($lat1, $long1, $lat2, $long2)
    {
        $base_uri = 'https://maps.googleapis.com/maps/api/distancematrix/json';
        $google_api_key = config('app.google_api_key');

        $client = new Client();
        try {
            $res = $client->request('GET', $base_uri, [
                'query' => [
                    'key' => $google_api_key,
                    'origins' => $lat1 . ',' . $long1,
                    'destinations' => $lat2 . ',' . $long2,
                ]
            ]);
        } catch (RequestException $e) {
            echo Psr7\Message::toString($e->getRequest());
            if ($e->hasResponse()) {
                echo Psr7\Message::toString($e->getResponse());
            }
        } catch (ConnectException $e) {
            return "Connection Error";
        }

        $response = json_decode($res->getBody(), true);

        if ($response['status'] == 'REQUEST_DENIED') {
            return $response['error_message'];
        }

        $distant = $response['rows'][0]['elements'][0]['distance']['value'];

        return $distant;
    }

}
