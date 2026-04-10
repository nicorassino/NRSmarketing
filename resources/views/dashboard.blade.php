<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dashboard NRSMarketing
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <a href="{{ route('products.index') }}" class="bg-white p-5 rounded-lg shadow hover:shadow-md transition">
                    <p class="text-sm text-gray-500">Productos</p>
                    <p class="text-2xl font-semibold text-gray-800">{{ \App\Models\Product::count() }}</p>
                </a>
                <a href="{{ route('campaigns.index') }}" class="bg-white p-5 rounded-lg shadow hover:shadow-md transition">
                    <p class="text-sm text-gray-500">Campanas</p>
                    <p class="text-2xl font-semibold text-gray-800">{{ \App\Models\Campaign::count() }}</p>
                </a>
                <a href="{{ route('campaigns.index') }}" class="bg-white p-5 rounded-lg shadow hover:shadow-md transition">
                    <p class="text-sm text-gray-500">Prospectos</p>
                    <p class="text-2xl font-semibold text-gray-800">{{ \App\Models\Prospect::count() }}</p>
                </a>
                <a href="{{ route('whatsapp.bridge.show') }}" class="bg-white p-5 rounded-lg shadow hover:shadow-md transition">
                    <p class="text-sm text-gray-500">WhatsApp Bridge</p>
                    <p class="text-base font-semibold text-gray-800">Estado</p>
                </a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 space-y-2">
                    <p class="font-semibold">Presupuesto API mensual</p>
                    <p class="text-sm text-gray-600">
                        Consumido: USD {{ number_format(\App\Models\ApiUsageLog::currentMonthCost(), 4) }}
                    </p>
                    <p class="text-sm text-gray-600">
                        Disponible: USD {{ number_format(\App\Models\ApiUsageLog::remainingBudget(), 4) }}
                    </p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-3">
                    <p class="font-semibold">Flujo recomendado</p>
                    <ol class="list-decimal ml-5 text-sm text-gray-700 space-y-1">
                        <li>Crear producto y subir documentos.</li>
                        <li>Ejecutar Analyst desde el detalle del producto.</li>
                        <li>Abrir campana y ejecutar Scout.</li>
                        <li>Revisar Inbox, aprobar mensajes y lanzar Executor.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
