<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Chat del producto: {{ $product->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white shadow sm:rounded-lg p-6">
                <p class="text-sm text-gray-500">Conversacion</p>
                <p class="font-medium">{{ $conversation->title }}</p>
                <p class="text-xs text-gray-500 mt-1">Mensajes: {{ $messages->count() }}</p>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6 space-y-4">
                @forelse ($messages as $msg)
                    <div class="{{ $msg->role === 'assistant' ? 'bg-gray-50 border-gray-200' : 'bg-indigo-50 border-indigo-200' }} border rounded p-3">
                        <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">
                            {{ $msg->role }}
                        </div>
                        <div class="text-sm text-gray-800 whitespace-pre-wrap">{{ $msg->content }}</div>
                        @if ($msg->role === 'assistant')
                            <div class="text-xs text-gray-500 mt-2">
                                in: {{ $msg->input_tokens }} | out: {{ $msg->output_tokens }} | usd: {{ $msg->cost_usd }}
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Todavia no hay mensajes. Envia el primero.</p>
                @endforelse
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <form method="POST" action="{{ route('products.chat.send', $product) }}" class="space-y-3">
                    @csrf
                    <label class="block text-sm font-medium text-gray-700">Tu mensaje</label>
                    <textarea
                        name="content"
                        rows="5"
                        class="w-full border-gray-300 rounded-md"
                        placeholder="Ejemplo: analizame los resultados del ultimo run y proponeme un nuevo enfoque para el Scout."
                        required
                    ></textarea>
                    <button class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                        Enviar al chat
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
