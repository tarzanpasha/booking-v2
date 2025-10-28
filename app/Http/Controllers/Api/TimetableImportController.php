<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Actions\CreateTimetableFromJsonAction;
use App\Http\Requests\ImportTimetableRequest;
use App\Http\Resources\TimetableResource;
use Illuminate\Http\JsonResponse;

class TimetableImportController extends Controller
{
    public function __construct(
        private CreateTimetableFromJsonAction $createTimetableFromJsonAction
    ) {}

    public function importFromJson(ImportTimetableRequest $request): JsonResponse
    {
        try {
            $timetable = $this->createTimetableFromJsonAction->execute(
                $request->company_id,
                $request->schedule_data,
                $request->type
            );

            return response()->json([
                'data' => new TimetableResource($timetable),
                'message' => 'Timetable imported successfully from JSON'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function importFromFile(ImportTimetableRequest $request): JsonResponse
    {
        try {
            $fileContent = file_get_contents($request->file('schedule_file')->getPathname());
            $scheduleData = json_decode($fileContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON file: ' . json_last_error_msg());
            }

            $timetable = $this->createTimetableFromJsonAction->execute(
                $request->company_id,
                $scheduleData,
                $request->type
            );

            return response()->json([
                'data' => new TimetableResource($timetable),
                'message' => 'Timetable imported successfully from file'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
