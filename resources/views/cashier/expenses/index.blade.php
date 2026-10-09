<x-app-layout title="Expenses" header="Expenses">

    <div class="mb-4 flex justify-end">
        <button type="button"
            x-data=""
            x-on:click="$dispatch('open-modal', 'record-expense')"
            class="bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg">
            Record Expenses
        </button>
    </div>

    <x-modal name="record-expense" :show="$errors->any()" maxWidth="lg" focusable>
        <div class="p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">Record Expense</h2>
                <button type="button" x-on:click="$dispatch('close-modal', 'record-expense')"
                    class="text-gray-400 hover:text-gray-600" aria-label="Close">
                    &times;
                </button>
            </div>
            <form method="POST" action="{{ route('cashier.expenses.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label for="expense-category" class="block text-xs font-medium text-gray-600 mb-1">Category</label>
                    <select id="expense-category" name="category" required
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                        <option value="">Select a category</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->name }}" @selected(old('category') === $category->name)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Amount (₱)</label>
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Date</label>
                    <input type="date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" required
                        class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Note (optional)</label>
                    <textarea name="note" rows="2" class="w-full rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">{{ old('note') }}</textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" x-on:click="$dispatch('close-modal', 'record-expense')"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="bg-maroon-600 hover:bg-maroon-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg">
                    Add Expense
                    </button>
                </div>
            </form>
        </div>
    </x-modal>

    <div>
            <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
                    <input type="date" name="from" value="{{ request('from') }}" class="rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
                    <input type="date" name="to" value="{{ request('to') }}" class="rounded-lg border-gray-300 text-sm focus:border-maroon-500 focus:ring-maroon-500">
                </div>
                <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg">Filter</button>
                @if(request('from') || request('to'))
                <a href="{{ route('cashier.expenses.index') }}" class="text-sm text-gray-400 hover:text-gray-600 px-2 py-2">Clear</a>
                @endif
                <div class="ml-auto text-sm text-gray-600">Total: <span class="font-semibold text-gray-900">₱{{ number_format($total, 2) }}</span></div>
            </form>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-gray-500 border-b border-gray-100">
                                <th class="py-3 px-4 font-medium">ID</th>
                                <th class="py-3 px-4 font-medium">Date</th>
                                <th class="py-3 px-4 font-medium">Category</th>
                                <th class="py-3 px-4 font-medium">Amount</th>
                                <th class="py-3 px-4 font-medium">Note</th>
                                <th class="py-3 px-4 font-medium">By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expenses as $expense)
                            <tr class="border-b border-gray-50 hover:bg-gray-50">
                                <td class="py-2.5 px-4 text-gray-500">EXP-{{ str_pad((string) $expense->id, 6, '0', STR_PAD_LEFT) }}</td>
                                <td class="py-2.5 px-4 text-gray-500">{{ $expense->expense_date->format('M j, Y') }}</td>
                                <td class="py-2.5 px-4 text-gray-800">{{ $expense->category }}</td>
                                <td class="py-2.5 px-4 text-gray-700">₱{{ number_format($expense->amount, 2) }}</td>
                                <td class="py-2.5 px-4 text-gray-500">{{ $expense->note ?: '—' }}</td>
                                <td class="py-2.5 px-4 text-gray-500">{{ $expense->creator->name ?? '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-gray-400">No expenses recorded.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($expenses->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">{{ $expenses->links() }}</div>
                @endif
            </div>
    </div>

</x-app-layout>