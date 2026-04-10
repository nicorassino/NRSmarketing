<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Producto: {{ $product->name }}
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
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Marca</p>
                        <p class="text-sm text-gray-800">{{ $product->brand_name ?: '-' }}</p>
                        <p class="text-sm text-gray-500 mt-2">Producto</p>
                        <p class="text-sm text-gray-800">{{ $product->name ?: '-' }}</p>
                        <p class="text-sm text-gray-500">Descripcion</p>
                        <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ $product->description ?: '-' }}</p>
                        <p class="text-sm text-gray-500 mt-2">Propuesta de valor</p>
                        <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ $product->value_proposition ?: '-' }}</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <form method="POST" action="{{ route('products.generate_positioning', $product) }}">
                            @csrf
                            <button class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-500">
                                Generar descripcion/propuesta con Gemini Pro
                            </button>
                        </form>
                        <form method="POST" action="{{ route('products.analyze', $product) }}">
                            @csrf
                            <button class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                                Ejecutar Analyst
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Editar producto</h3>
                <form method="POST" action="{{ route('products.update', $product) }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @csrf
                    @method('PATCH')
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Marca</label>
                        <input type="text" name="brand_name" value="{{ $product->brand_name }}" class="w-full border-gray-300 rounded-md">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del producto</label>
                        <input type="text" name="name" value="{{ $product->name }}" class="w-full border-gray-300 rounded-md" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                        <select name="status" class="w-full border-gray-300 rounded-md" required>
                            <option value="active" @selected($product->status === 'active')>active</option>
                            <option value="archived" @selected($product->status === 'archived')>archived</option>
                        </select>
                    </div>
                    <div></div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descripcion</label>
                        <textarea name="description" rows="4" class="w-full border-gray-300 rounded-md">{{ $product->description }}</textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Propuesta de valor</label>
                        <textarea name="value_proposition" rows="3" class="w-full border-gray-300 rounded-md">{{ $product->value_proposition }}</textarea>
                    </div>
                    <div class="md:col-span-2">
                        <button class="px-4 py-2 text-xs bg-gray-800 text-white rounded">Guardar cambios del producto</button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Cargar documento</h3>
                <form method="POST" action="{{ route('products.documents.store', $product) }}" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Titulo</label>
                        <input type="text" name="title" class="w-full border-gray-300 rounded-md" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                        <select name="type" class="w-full border-gray-300 rounded-md" required>
                            <option value="manual">manual</option>
                            <option value="brochure">brochure</option>
                            <option value="specs">specs</option>
                            <option value="other">other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Archivo</label>
                        <input type="file" name="file" class="w-full text-sm" required>
                    </div>
                    <div class="md:col-span-3">
                        <button class="px-4 py-2 text-xs bg-gray-800 text-white rounded">Subir documento</button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Pegar contexto en texto</h3>
                <form method="POST" action="{{ route('products.context_text.store', $product) }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Titulo</label>
                        <input type="text" name="title" class="w-full border-gray-300 rounded-md" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                        <select name="type" class="w-full border-gray-300 rounded-md" required>
                            <option value="manual">manual</option>
                            <option value="brochure">brochure</option>
                            <option value="specs">specs</option>
                            <option value="other">other</option>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contenido</label>
                        <textarea name="content" rows="8" class="w-full border-gray-300 rounded-md" placeholder="Pega aqui documentacion, speech comercial, FAQs, casos de uso, etc." required></textarea>
                    </div>
                    <div class="md:col-span-3">
                        <button class="px-4 py-2 text-xs bg-indigo-600 text-white rounded">Guardar texto como contexto</button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <div>
                        <h3 class="text-lg font-semibold">Estrategia de mensajes base (IA senior)</h3>
                        <p class="text-sm text-gray-500">
                            Genera opciones profesionales por canal, elige una, editala y guardala como plantilla maestra.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('products.message_templates.generate', $product) }}">
                        @csrf
                        <button class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-500">
                            Generar opciones con Gemini Pro
                        </button>
                    </form>
                </div>

                <div class="mb-4 text-xs text-gray-600 bg-gray-50 border rounded p-3">
                    Placeholders disponibles: <code>@{{company_name}}</code>, <code>@{{brand_name}}</code>,
                    <code>@{{product_name}}</code>, <code>@{{value_proposition}}</code>, <code>@{{analysis_hint}}</code>
                </div>

                @php
                    $groupedTemplates = $product->messageTemplates->groupBy('channel');
                @endphp

                <div class="space-y-6">
                    @foreach (['whatsapp', 'instagram', 'email'] as $channel)
                        <div class="border rounded p-4">
                            <h4 class="font-semibold capitalize mb-3">{{ $channel }}</h4>

                            @forelse (($groupedTemplates[$channel] ?? collect()) as $template)
                                <div class="border rounded p-3 mb-3 {{ $template->is_selected ? 'border-green-400 bg-green-50' : 'border-gray-200' }}">
                                    <div class="flex items-center justify-between gap-3 mb-2">
                                        <p class="text-sm font-medium">
                                            {{ $template->name }}
                                            @if ($template->is_selected)
                                                <span class="ml-2 text-xs text-green-700">(Seleccionada)</span>
                                            @endif
                                        </p>
                                        <form method="POST" action="{{ route('message_templates.select', $template) }}">
                                            @csrf
                                            <button class="px-3 py-1 text-xs rounded border {{ $template->is_selected ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-700' }}">
                                                Usar esta
                                            </button>
                                        </form>
                                    </div>

                                    <form method="POST" action="{{ route('message_templates.update', $template) }}" class="space-y-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="name" value="{{ $template->name }}" class="w-full border-gray-300 rounded-md text-sm" required>
                                        @if ($channel === 'email')
                                            <input type="text" name="subject" value="{{ $template->subject }}" class="w-full border-gray-300 rounded-md text-sm" placeholder="Asunto email">
                                        @endif
                                        <textarea name="content" rows="6" class="w-full border-gray-300 rounded-md text-sm" required>{{ $template->content }}</textarea>
                                        <button class="px-3 py-1 text-xs bg-gray-800 text-white rounded">Guardar plantilla</button>
                                    </form>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">Aun no hay plantillas para este canal.</p>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Documentos</h3>
                <ul class="space-y-3">
                    @forelse ($product->documents as $doc)
                        <li class="border rounded p-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-medium">{{ $doc->title }}</p>
                                    <p class="text-xs text-gray-500">{{ $doc->type }} - {{ $doc->original_filename }}</p>
                                </div>
                                <span class="text-xs text-gray-500">
                                    {{ $doc->extracted_text ? 'Texto indexado' : 'Sin texto indexado' }}
                                </span>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500">No hay documentos cargados.</li>
                    @endforelse
                </ul>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Campanas del producto</h3>
                <ul class="space-y-3">
                    @forelse ($product->campaigns as $campaign)
                        <li class="border rounded p-3 flex items-center justify-between gap-3">
                            <div>
                                <p class="font-medium">{{ $campaign->name }}</p>
                                <p class="text-xs text-gray-500">{{ $campaign->status_label }}</p>
                            </div>
                            <a href="{{ route('campaigns.show', $campaign) }}" class="text-indigo-600 hover:text-indigo-800 text-sm">Ver campana</a>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500">Sin campanas asociadas.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
