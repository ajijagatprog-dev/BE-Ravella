<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Address;

class CustomerController extends Controller
{
    // GET: /api/customer/profile
    public function getProfile(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => $request->user()
        ]);
    }

    // PUT: /api/customer/profile
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,'.$user->id,
            'phone_number' => 'sometimes|nullable|string',
            'address' => 'sometimes|nullable|string',
        ]);

        $user->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'data' => $user
        ]);
    }

    // GET: /api/customer/addresses
    public function getAddresses(Request $request)
    {
        $addresses = $request->user()->addresses()->orderBy('is_primary', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'data' => $addresses
        ]);
    }

    // POST: /api/customer/addresses
    public function addAddress(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'label'            => 'required|string',
            'recipient_name'   => 'required|string',
            'phone_number'     => 'required|string',
            'full_address'     => 'required|string',
            'city'             => 'required|string',
            'province'         => 'required|string',
            'postal_code'      => 'required|string',
            'is_primary'       => 'boolean',
            // RajaOngkir IDs (opsional, tapi dibutuhkan untuk cek ongkir)
            'province_id'      => 'nullable|integer',
            'city_id'          => 'nullable|integer',
            'subdistrict_id'   => 'nullable|integer',
            'subdistrict_name' => 'nullable|string',
        ]);

        if (empty($user->addresses) || $request->is_primary) {
            $user->addresses()->update(['is_primary' => false]);
            $validated['is_primary'] = true;
        }

        $address = $user->addresses()->create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Address added successfully',
            'data' => $address
        ], 201);
    }

    // PUT: /api/customer/addresses/{id}
    public function updateAddress(Request $request, $id)
    {
        $user = $request->user();
        $address = $user->addresses()->findOrFail($id);
        
        $validated = $request->validate([
            'label'            => 'sometimes|string',
            'recipient_name'   => 'sometimes|string',
            'phone_number'     => 'sometimes|string',
            'full_address'     => 'sometimes|string',
            'city'             => 'sometimes|string',
            'province'         => 'sometimes|string',
            'postal_code'      => 'sometimes|string',
            'is_primary'       => 'boolean',
            // RajaOngkir IDs
            'province_id'      => 'nullable|integer',
            'city_id'          => 'nullable|integer',
            'subdistrict_id'   => 'nullable|integer',
            'subdistrict_name' => 'nullable|string',
        ]);

        if (isset($validated['is_primary']) && $validated['is_primary']) {
            $user->addresses()->update(['is_primary' => false]);
        }

        $address->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Address updated successfully',
            'data' => $address
        ]);
    }

    // DELETE: /api/customer/addresses/{id}
    public function deleteAddress(Request $request, $id)
    {
        $user = $request->user();
        $address = $user->addresses()->findOrFail($id);

        $wasPrimary = $address->is_primary;
        $address->delete();

        if ($wasPrimary) {
            $newPrimary = $user->addresses()->first();
            if ($newPrimary) {
                $newPrimary->update(['is_primary' => true]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Address deleted successfully'
        ]);
    }

    // PUT: /api/customer/addresses/{id}/primary
    public function setPrimaryAddress(Request $request, $id)
    {
        $user = $request->user();
        $address = $user->addresses()->findOrFail($id);

        $user->addresses()->update(['is_primary' => false]);
        $address->update(['is_primary' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Primary address updated'
        ]);
    }
}
