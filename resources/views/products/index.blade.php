<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Productos
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
                <h3 class="text-lg font-semibold mb-4">Nuevo producto</h3>
                <form method="POST" action="{{ route('products.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Marca</label>
                        <input type="text" name="brand_name" class="w-full border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                        <input type="text" name="name" class="w-full border-gray-300 rounded-md" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descripcion</label>
                        <textarea name="description" rows="3" class="w-full border-gray-300 rounded-md"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Propuesta de valor</label>
                        <textarea name="value_proposition" rows="3" class="w-full border-gray-300 rounded-md"></textarea>
                    </div>
                    <button class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                        Guardar producto
                    </button>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Listado</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="py-2 pr-4">Marca</th>
                                <th class="py-2 pr-4">Nombre</th>
                                <th class="py-2 pr-4">Estado</th>
                                <th class="py-2">Analizado</th>
                                <th class="py-2">Docs</th>
                                <th class="py-2">Chat</th>
                                <th class="py-2">Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($products as $product)
                                <tr class="border-b">
                                    <td class="py-2 pr-4">{{ $product->brand_name ?: '-' }}</td>
                                    <td class="py-2 pr-4">{{ $product->name }}</td>
                                    <td class="py-2 pr-4">{{ $product->status }}</td>
                                    <td class="py-2">{{ $product->is_analyzed ? 'Si' : 'No' }}</td>
                                    <td class="py-2">{{ $product->documents_count }}</td>
                                    <td class="py-2">
                                        <a href="{{ route('products.chat.show', $product) }}" class="text-indigo-600 hover:text-indigo-800">
                                            Abrir chat
                                        </a>
                                    </td>
                                    <td class="py-2">
                                        <a href="{{ route('products.show', $product) }}" class="text-indigo-600 hover:text-indigo-800">
                                            Gestionar
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-4 text-gray-500">No hay productos todavia.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
