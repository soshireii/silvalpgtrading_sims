<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Delivery staff are NOT permanent employees, so unlike the Owner/Cashier accounts
 * (seeded once), their accounts only exist if the Owner creates them here.
 * There is no public registration route — see routes/auth.php.
 */
class DeliveryStaffController extends Controller
{
    public function index()
    {
        $staff = User::deliveryStaff()
            ->withCount([
                'deliveries as active_deliveries_count' => fn ($q) => $q->where('status', 'out_for_delivery'),
                'deliveries as completed_deliveries_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->orderByDesc('is_active')
            ->orderByName()
            ->get();

        return view('admin.delivery-staff.index', compact('staff'));
    }

    public function create()
    {
        return view('admin.delivery-staff.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'required|string|max:30',
            'password' => 'required|string|min:8|confirmed',
        ]);

        User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => 'delivery',
            'is_active' => true,
            'email_verified_at' => now(), // owner-created accounts are trusted immediately
        ]);

        return redirect()->route('admin.delivery-staff.index')
            ->with('success', 'Delivery staff account created. Share the login credentials with them directly.');
    }

    public function edit(User $deliveryStaff)
    {
        abort_unless($deliveryStaff->role === 'delivery', 404);
        return view('admin.delivery-staff.edit', ['staff' => $deliveryStaff]);
    }

    public function update(Request $request, User $deliveryStaff)
    {
        abort_unless($deliveryStaff->role === 'delivery', 404);

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($deliveryStaff->id)],
            'phone' => 'required|string|max:30',
            'is_active' => 'required|boolean',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $deliveryStaff->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'is_active' => $validated['is_active'],
        ]);

        if (! empty($validated['password'])) {
            $deliveryStaff->update(['password' => Hash::make($validated['password'])]);
        }

        return back()->with('success', 'Delivery staff account updated.');
    }

    /**
     * Blocked only while they still have a delivery in progress, so an order is never
     * left pointing at a deleted user. Past orders and photo proofs keep their history
     * (the link to the deleted account is just cleared).
     */
    public function destroy(User $deliveryStaff)
    {
        abort_unless($deliveryStaff->role === 'delivery', 404);

        $activeDeliveries = $deliveryStaff->deliveries()->where('status', 'out_for_delivery')->count();

        if ($activeDeliveries > 0) {
            throw ValidationException::withMessages([
                'delivery' => "Cannot delete: this staff member has {$activeDeliveries} delivery(ies) currently out for delivery. Reassign or wait for completion first.",
            ]);
        }

        $deliveryStaff->deliveries()->update(['assigned_delivery_id' => null]);
        $deliveryStaff->delete();

        return redirect()->route('admin.delivery-staff.index')
            ->with('success', 'Delivery staff account deleted.');
    }
}
