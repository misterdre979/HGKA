<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\HostelAllocation;
use App\Models\Term;
use App\Models\TransportAllocation;
use App\Models\TransportRoute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Read models for transport and hostel screens. */
class FacilitiesController extends Controller
{
    public function hostelOccupancy(): JsonResponse
    {
        $active = HostelAllocation::where('status', 'active')->selectRaw('hostel_room_id, count(*) as n')->groupBy('hostel_room_id')->pluck('n', 'hostel_room_id');

        return response()->json(Hostel::with('rooms')->orderBy('name')->get()->map(function ($h) use ($active) {
            $rooms = $h->rooms->map(fn ($r) => [
                'id' => $r->id, 'room_no' => $r->room_no, 'capacity' => $r->capacity,
                'occupied' => (int) ($active[$r->id] ?? 0), 'available' => $r->capacity - (int) ($active[$r->id] ?? 0),
            ]);

            return [
                'id' => $h->id, 'name' => $h->name, 'gender' => $h->gender, 'rooms' => $rooms,
                'capacity' => $rooms->sum('capacity'), 'occupied' => $rooms->sum('occupied'),
            ];
        }));
    }

    public function roomOccupants(int $room): JsonResponse
    {
        return response()->json(
            HostelAllocation::with('student:id,admission_no,first_name,last_name')->where(['hostel_room_id' => $room, 'status' => 'active'])->orderBy('bed_no')->get()
        );
    }

    /** Students riding a route this term. */
    public function routeRoster(Request $request, TransportRoute $transportRoute): JsonResponse
    {
        $termId = $request->query('term_id') ?: Term::where('is_current', true)->value('id');

        return response()->json([
            'route' => $transportRoute->load('vehicle.driver'),
            'students' => TransportAllocation::with('student:id,admission_no,first_name,last_name,phone')
                ->where(['transport_route_id' => $transportRoute->id, 'term_id' => $termId, 'status' => 'active'])->get(),
        ]);
    }
}
