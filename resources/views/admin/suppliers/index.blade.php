<x-app-layout title="Suppliers" header="Suppliers">

    <div x-data="{
            form: @js(old('name') !== null
                ? ['id' => old('_id') ?: null, 'name' => old('name'), 'contact_first_name' => old('contact_first_name'), 'contact_last_name' => old('contact_last_name'),
                   'phone' => old('phone'), 'email' => old('email'), 'address' => old('address'), 'is_active' => (int) old('is_active', 1)]
                : ['id' => null, 'name' => '', 'contact_first_name' => '', 'contact_last_name' => '', 'phone' => '', 'email' => '', 'address' => '', 'is_active' => 1]),
            updateUrl: @js(route('admin.suppliers.update', ['supplier' => '__ID__'])),
            fire(name, detail) { window.dispatchEvent(new CustomEvent(name, { detail: detail })); },
            add() { this.form = { id: null, name: '', contact_first_name: '', contact_last_name: '', phone: '', email: '', address: '', is_active: 1 }; this.fire('open-modal', 'supplier-form'); },
            edit(s) { this.form = { ...s, is_active: s.is_active ? 1 : 0 }; this.fire('open-modal', 'supplier-form'); },
            action() { return this.form.id ? this.updateUrl.replace('__ID__', this.form.id) : @js(route('admin.suppliers.store')); },
        }">

        <div class="mb-4 bg-maroon-50 border border-maroon-200 text-maroon-700 text-xs font-medium px-4 py-3 rounded-lg">
            Suppliers are who you buy LPG stock from. Pick one when you use <strong>Stock In from Supplier</strong>. A supplier that already has orders can't be deleted — set it to inactive instead.
        </div>

        <div class="flex justify-end mb-4">
            <button type="button" x-on:click="add()" class="inline-flex items-center gap-2 bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg shadow-sm transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                Add Supplier
            </button>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-gray-500 border-b border-gray-100">
                            <th class="py-3 px-4 font-medium">Supplier</th>
                            <th class="py-3 px-4 font-medium">Contact person</th>
                            <th class="py-3 px-4 font-medium">Phone / email</th>
                            <th class="py-3 px-4 font-medium">Orders</th>
                            <th class="py-3 px-4 font-medium">Status</th>
                            <th class="py-3 px-4 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($suppliers as $supplier)
                        <tr class="border-b border-gray-50 hover:bg-gray-50">
                            <td class="py-3 px-4 text-gray-800 font-medium">{{ $supplier->name }}
                                @if($supplier->address)<div class="text-xs font-normal text-gray-400">{{ $supplier->address }}</div>@endif
                            </td>
                            <td class="py-3 px-4 text-gray-600">{{ $supplier->contact_name ?? '—' }}</td>
                            <td class="py-3 px-4 text-gray-500">{{ $supplier->phone ?? '—' }}<div class="text-xs text-gray-400">{{ $supplier->email }}</div></td>
                            <td class="py-3 px-4 text-gray-600">{{ $supplier->purchase_orders_count }}</td>
                            <td class="py-3 px-4"><x-badge :color="$supplier->is_active ? 'green' : 'gray'" :label="$supplier->is_active ? 'Active' : 'Inactive'" /></td>
                            <td class="py-3 px-4 text-right space-x-3">
                                <button type="button" class="text-maroon-600 text-xs font-medium hover:underline"
                                        x-on:click="edit({{ \Illuminate\Support\Js::from($supplier->only(['id', 'name', 'contact_first_name', 'contact_last_name', 'phone', 'email', 'address', 'is_active'])) }})">Edit</button>
                                <form method="POST" action="{{ route('admin.suppliers.destroy', $supplier) }}" class="inline"
                                      onsubmit="return confirm('Delete this supplier?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-status-danger text-xs font-medium hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="py-8 text-center text-gray-400">No suppliers yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-modal name="supplier-form" :show="$errors->any()" maxWidth="lg" focusable>
            <form method="POST" :action="action()" class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="_id" :value="form.id">
                <template x-if="form.id"><input type="hidden" name="_method" value="PUT"></template>

                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900" x-text="form.id ? 'Edit supplier' : 'Add supplier'"></h2>
                    <button type="button" x-on:click="$dispatch('close-modal', 'supplier-form')" class="text-2xl leading-none text-gray-400 hover:text-gray-600" aria-label="Close">&times;</button>
                </div>

                @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-status-danger-bg px-4 py-3 text-sm font-medium text-status-danger">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
                @endif

                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Supplier / company name</label>
                    <input type="text" name="name" x-model="form.name" required maxlength="255" class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Contact first name</label>
                        <input type="text" name="contact_first_name" x-model="form.contact_first_name" maxlength="100" class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Contact last name</label>
                        <input type="text" name="contact_last_name" x-model="form.contact_last_name" maxlength="100" class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Phone</label>
                        <input type="text" name="phone" x-model="form.phone" maxlength="30" class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Email</label>
                        <input type="email" name="email" x-model="form.email" maxlength="255" class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-600">Address</label>
                    <input type="text" name="address" x-model="form.address" maxlength="255" class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
                <template x-if="form.id">
                    <div class="flex gap-4 text-sm">
                        <label class="flex items-center gap-2"><input type="radio" name="is_active" value="1" x-model="form.is_active" class="text-maroon-600 focus:ring-maroon-500"> Active</label>
                        <label class="flex items-center gap-2"><input type="radio" name="is_active" value="0" x-model="form.is_active" class="text-maroon-600 focus:ring-maroon-500"> Inactive</label>
                    </div>
                </template>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'supplier-form')" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg">Save supplier</button>
                </div>
            </form>
        </x-modal>
    </div>

</x-app-layout>
