<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Campanas
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Nueva campana</h3>

                @if ($products->isEmpty())
                    <p class="text-sm text-gray-600">
                        No hay productos activos. Crea uno antes de iniciar campanas.
                    </p>
                @else
                    <form method="POST" action="{{ route('campaigns.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Producto</label>
                            <select name="product_id" class="w-full border-gray-300 rounded-md" required>
                                <option value="">Seleccionar</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                            <input type="text" name="name" class="w-full border-gray-300 rounded-md" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nicho</label>
                            <input type="text" name="target_niche" class="w-full border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ubicacion</label>
                            <input type="text" name="target_location" class="w-full border-gray-300 rounded-md">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Objetivo</label>
                            <textarea name="objective" rows="3" class="w-full border-gray-300 rounded-md"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <button class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                                Crear campana
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Campanas existentes</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="py-2 pr-4">Campana</th>
                                <th class="py-2 pr-4">Producto</th>
                                <th class="py-2 pr-4">Estado</th>
                                <th class="py-2 pr-4">Ultimo run</th>
                                <th class="py-2">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($campaigns as $campaign)
                                <tr class="border-b">
                                    <td class="py-2 pr-4">{{ $campaign->name }}</td>
                                    <td class="py-2 pr-4">{{ $campaign->product?->name ?? '-' }}</td>
                                    <td class="py-2 pr-4">{{ $campaign->status_label }}</td>
                                    <td class="py-2 pr-4">
                                        @if ($campaign->latestRun)
                                            #{{ $campaign->latestRun->run_number }} ({{ $campaign->latestRun->status }})
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="py-2">
                                        <a href="{{ route('campaigns.show', $campaign) }}" class="text-indigo-600 hover:text-indigo-800">
                                            Ver detalle
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-gray-500">No hay campanas todavia.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
