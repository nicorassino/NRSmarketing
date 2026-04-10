<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            WhatsApp Bridge
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded">
                    {{ session('status') }}
                </div>
            @endif

            @if ($error)
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                    {{ $error }}
                </div>
            @endif

            <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Estado de sesion</p>
                        <p class="font-semibold">{{ $status['status'] ?? 'desconocido' }}</p>
                        @if (!empty($status['lastError']))
                            <p class="text-xs text-red-600 mt-1">{{ $status['lastError'] }}</p>
                        @elseif (!empty($status['last_error']))
                            <p class="text-xs text-red-600 mt-1">{{ $status['last_error'] }}</p>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('whatsapp.bridge.start') }}">
                        @csrf
                        <button class="px-4 py-2 text-xs bg-indigo-600 text-white rounded">Iniciar / reconectar</button>
                    </form>
                </div>

                @if (!empty($status['qr_data_url']))
                    <div>
                        <p class="text-sm text-gray-500 mb-2">Escanea este QR en WhatsApp Web</p>
                        <img src="{{ $status['qr_data_url'] }}" alt="QR WhatsApp" class="w-64 h-64 border rounded" />
                    </div>
                @endif

                <div class="text-xs text-gray-500">
                    Ultima actualizacion: {{ $status['last_updated_at'] ?? $status['lastUpdatedAt'] ?? '-' }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
