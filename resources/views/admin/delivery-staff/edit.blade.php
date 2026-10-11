<x-app-layout title="Edit {{ $staff->name }}" header="Edit Delivery Staff">

    <div class="max-w-lg bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <form method="POST" action="{{ route('admin.delivery-staff.update', $staff) }}" class="space-y-4">
            @csrf @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">First name</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $staff->first_name) }}" required maxlength="100"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Last name</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $staff->last_name) }}" required maxlength="100"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Email (used to log in)</label>
                <input type="email" name="email" value="{{ old('email', $staff->email) }}" required
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Phone number</label>
                <input type="text" name="phone" value="{{ old('phone', $staff->phone) }}" required
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                <div class="flex gap-4 mt-1">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="is_active" value="1" {{ old('is_active', $staff->is_active) ? 'checked' : '' }} class="text-maroon-600 focus:ring-maroon-500">
                        Active — can log in and be assigned deliveries
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="is_active" value="0" {{ ! old('is_active', $staff->is_active) ? 'checked' : '' }} class="text-maroon-600 focus:ring-maroon-500">
                        Deactivated — blocked from logging in
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-100">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Reset password (optional)</label>
                    <input type="password" name="password" minlength="8"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Confirm new password</label>
                    <input type="password" name="password_confirmation" minlength="8"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
            </div>
            <p class="text-[11px] text-gray-400">Leave the password fields blank to keep their current password.</p>

            <div class="pt-2 flex gap-3">
                <button type="submit" class="bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg shadow-sm">
                    Save Changes
                </button>
                <a href="{{ route('admin.delivery-staff.index') }}" class="text-sm text-gray-500 hover:text-gray-700 px-2 py-2.5">Cancel</a>
            </div>
        </form>
    </div>

</x-app-layout>