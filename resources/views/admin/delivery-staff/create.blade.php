<x-app-layout title="Add Delivery Staff" header="Add Delivery Staff">

    <div class="max-w-lg bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        <form method="POST" action="{{ route('admin.delivery-staff.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">First name</label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}" required maxlength="100"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Last name</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}" required maxlength="100"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Email (used to log in)</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Phone number</label>
                <input type="text" name="phone" value="{{ old('phone') }}" required
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Temporary password</label>
                    <input type="password" name="password" required minlength="8"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Confirm password</label>
                    <input type="password" name="password_confirmation" required minlength="8"
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
            </div>
            <p class="text-[11px] text-gray-400">Share this password with the staff member directly — there's no self-signup, so this is the only way they get access.</p>

            <div class="pt-2 flex gap-3">
                <button type="submit" class="bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg shadow-sm">
                    Create Account
                </button>
                <a href="{{ route('admin.delivery-staff.index') }}" class="text-sm text-gray-500 hover:text-gray-700 px-2 py-2.5">Cancel</a>
            </div>
        </form>
    </div>

</x-app-layout>