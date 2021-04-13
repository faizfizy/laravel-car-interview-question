# Laravel Car Interview Question

The Car Solutions owns multiple workshops located at different locations with different working hours.

The company is currently building a platform for its admin staff in order to schedule booking appointments with different workshops.

Whenever a client called in to schedule for an appointment. The admin staff needs to be able to check the availability of the workshops, make recommendations and also create a new appointment.

The staff at the workshop will need to have access to the appointments on daily basis in order to prepare the required parts and tools.

## Your task

Create endpoints that allows the admin staff to:

1. List down all the appointments with ability to filter by each workshop based on provided workshop_id

2. Schedule an appointment based on client's request

  - It should be able to create a new appointment based on given information

  - Other than that, it should also detect the availablility of the workshop and prevent appointments with overlapping time from being created.

3. Recommend the workshops based on the availability and the locations

  - The endpoint should be able to recommend workshops based on

    1. Availability (Show workshop that do not have appointment during the provided time)

    2. Location (Sort the workshop based on the distance)

## Notes

- Feel free include any assumptions or notes that you have

- Please include any instructions or guides that you have in order for us to test the work that you have done

- We like tests, include tests in your code will be advantageous

## Setup

- Please refer to https://laravel.com/docs/8.x/installation on how to set it up and running in you machine

- Once you have the environment up, run `scripts/setup` to setup the database and run the migration

- In order to seed the data, please run `php artisan db:seed`

## Send the answer back to us

1. Checkout and work on your branch

2. Commit as you progress

3. Once you are done, generate the patch file by using

```
git format-patch develop
```

4. Send the patch file back to us

# Answer

## Assumptions & Limitations
- Every workshop may only service one car at a time.
- Every service duration will take about one hour time.
- Workshop operating hours will always be within the same day.

## Setup
- Please define `GOOGLE_MAPS_API_KEY` in the .env file to calculate distance using Google Distance Matrix API. If no API_KEY is specified, the distance will be calculated using Haversine formula.

## Available Endpoints
1. GET /appointments
    - Parameters:
      - `workshop_id` (Optional. If no value specified, it will return for all workshops)
      - `date` (Optional. If no value specified, it will only return future appointments)

2. POST /appointment
     - Parameters:
        - `car_id` (Required)
        - `workshop_id` (Required)
        - `start_time` (Required)
        - `end_time` (Required)
     - Note: 
       - `start_time` and `end_time` must be after current datetime
       - New appointment will not be allowed if it overlaps with existing appointment
       - If new appointment is successfully created, it will return the appointment details
3. GET /appointment/recommend
    - Parameters
        - `car_id` (Required)
        - `distance_calculation` (Optional. If not specified, it will use `google` if `GOOGLE_MAPS_API_KEY` is specified)
          - `local` - Using Haversine formula
          - `google` - Using Google Distance Matrix API
    - Note:
        - Distance will always be return in `metres`
    
## Tests
- No test provided
