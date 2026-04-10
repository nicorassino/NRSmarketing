<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Inbox Run #{{ $run->run_number }} - {{ $run->campaign->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                    {{ session('status') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded text-sm">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Producto</p>
                        <p class="font-medium">{{ $run->campaign->product?->name ?? '-' }}</p>
                        <p class="text-xs text-gray-500 mt-1">
                            Mostrando: {{ $stats['total'] ?? 0 }} / {{ $stats['total_all'] ?? 0 }} |
                            Aprobados: {{ $stats['approved'] ?? 0 }} |
                            Con canal: {{ $stats['with_channel'] ?? 0 }} |
                            Mensajes aprobados: {{ $stats['approved_messages'] ?? 0 }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('runs.inbox.generate_drafts', $run) }}">
                            @csrf
                            <button class="inline-flex items-center px-4 py-2 bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600">
                                Generar borradores
                            </button>
                        </form>
                        <form id="inbox-review-form" method="POST" action="{{ route('runs.inbox.review_drafts', $run) }}">
                            @csrf
                            <input type="hidden" name="contact_filter" value="{{ $contactFilter }}">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-500">
                                Revisar borradores (IA)
                            </button>
                        </form>
                        <form method="POST" action="{{ route('runs.executor', $run) }}">
                            @csrf
                            <button class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500">
                                Enviar aprobados (Executor)
                            </button>
                        </form>
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">Marcá empresas en la primera columna y usá <strong>Revisar borradores</strong> para optimizar solo el mensaje del <strong>canal elegido</strong> (debe estar guardado en la fila).</p>
                <div class="mt-3 flex items-center gap-2 text-xs">
                    <a href="{{ route('runs.inbox', ['run' => $run->id, 'contact_filter' => 'all']) }}" class="px-2 py-1 rounded {{ $contactFilter === 'all' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700' }}">Todos</a>
                    <a href="{{ route('runs.inbox', ['run' => $run->id, 'contact_filter' => 'with_contact']) }}" class="px-2 py-1 rounded {{ $contactFilter === 'with_contact' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700' }}">Con contacto</a>
                    <a href="{{ route('runs.inbox', ['run' => $run->id, 'contact_filter' => 'without_contact']) }}" class="px-2 py-1 rounded {{ $contactFilter === 'without_contact' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700' }}">Sin contacto</a>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left border-b">
                            <th class="py-2 pr-2 w-10" title="Seleccionar para revisión IA"></th>
                            <th class="py-2 pr-3">Empresa</th>
                            <th class="py-2 pr-3">Contacto</th>
                            <th class="py-2 pr-3">Score</th>
                            <th class="py-2 pr-3">Estado/Canal</th>
                            <th class="py-2 pr-3">Mensajes</th>
                            <th class="py-2">Guardar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($prospects as $prospect)
                            <tr class="border-b align-top">
                                <td class="py-3 pr-2 align-middle">
                                    <input type="checkbox" name="prospect_ids[]" value="{{ $prospect->id }}" form="inbox-review-form" class="rounded border-gray-300">
                                </td>
                                <td class="py-3 pr-3">
                                    <div class="font-medium">{{ $prospect->company_name }}</div>
                                    <div class="text-xs text-gray-500">{{ $prospect->website_url }}</div>
                                </td>
                                <td class="py-3 pr-3">
                                    <div>{{ $prospect->contact_name ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ $prospect->email ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ $prospect->phone ?? '-' }}</div>
                                </td>
                                <td class="py-3 pr-3">
                                    <div>{{ $prospect->score }}</div>
                                    <div class="text-xs text-gray-500">CR: {{ $prospect->raw_data['contact_richness'] ?? 0 }}</div>
                                </td>
                                <td class="py-3 pr-3">
                                    <form method="POST" action="{{ route('prospects.update', $prospect) }}" class="space-y-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="w-full border-gray-300 rounded-md text-xs">
                                            @foreach (['new' => 'Nuevo', 'approved' => 'Aprobado', 'rejected' => 'Rechazado'] as $value => $label)
                                                <option value="{{ $value }}" @selected($prospect->status === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <select name="selected_channel" class="w-full border-gray-300 rounded-md text-xs">
                                            <option value="">Sin canal</option>
                                            @foreach (['whatsapp', 'email', 'instagram'] as $channel)
                                                <option value="{{ $channel }}" @selected($prospect->selected_channel === $channel)>{{ $channel }}</option>
                                            @endforeach
                                        </select>
                                        <button class="text-xs px-3 py-2 bg-gray-800 text-white rounded">Guardar</button>
                                    </form>
                                </td>
                                <td class="py-3 pr-3">
                                    <div class="space-y-2">
                                        @forelse ($prospect->messages as $message)
                                            <div class="border rounded p-2">
                                                <div class="flex items-center justify-between text-xs mb-1 gap-2 flex-wrap">
                                                    <span class="font-semibold">{{ $message->channel }}</span>
                                                    <div class="flex items-center gap-2 flex-wrap">
                                                        @if ($message->ai_inbox_reviewed_at)
                                                            @if ($message->ai_inbox_suggest_send === true)
                                                                <span class="px-2 py-0.5 rounded bg-green-100 text-green-800 font-medium">IA: apto enviar</span>
                                                            @elseif ($message->ai_inbox_suggest_send === false)
                                                                <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-900 font-medium">IA: revisar</span>
                                                            @else
                                                                <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-600">IA revisado</span>
                                                            @endif
                                                        @endif
                                                        <span class="text-gray-500">{{ $message->status }}</span>
                                                    </div>
                                                </div>
                                                @if ($message->ai_inbox_review_notes)
                                                    <p class="text-xs text-gray-500 mb-2">{{ $message->ai_inbox_review_notes }}</p>
                                                @endif
                                                @if ($message->status === \App\Models\ProspectMessage::STATUS_FAILED)
                                                    @php
                                                        $meta = $message->delivery_metadata ?? [];
                                                        $sendErr = $meta['error'] ?? null;
                                                    @endphp
                                                    @if ($sendErr)
                                                        <div class="text-xs text-red-700 bg-red-50 border border-red-100 rounded px-2 py-1.5 mb-2 whitespace-pre-wrap" title="Respuesta del canal / bridge">
                                                            <strong>Envío fallido:</strong> {{ is_array($sendErr) ? json_encode($sendErr, JSON_UNESCAPED_UNICODE) : $sendErr }}
                                                            @if (!empty($meta['http_status']))
                                                                <span class="text-red-600">(HTTP {{ $meta['http_status'] }})</span>
                                                            @endif
                                                        </div>
                                                    @else
                                                        <div class="text-xs text-red-600 mb-2">Envío fallido (sin detalle guardado). Revisá bridge WhatsApp, token y número en formato internacional.</div>
                                                    @endif
                                                @endif
                                                <form method="POST" action="{{ route('messages.update', $message) }}" class="space-y-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    @if ($message->channel === 'email')
                                                        <input type="text" name="subject" value="{{ $message->subject }}" class="w-full border-gray-300 rounded-md text-xs" placeholder="Asunto email">
                                                    @endif
                                                    <textarea name="content" rows="4" class="w-full border-gray-300 rounded-md text-xs">{{ $message->content }}</textarea>
                                                    <button class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-700">Guardar borrador</button>
                                                </form>
                                                @if ($message->status !== \App\Models\ProspectMessage::STATUS_APPROVED)
                                                    <form method="POST" action="{{ route('messages.approve', $message) }}" class="mt-2">
                                                        @csrf
                                                        <button class="text-xs px-2 py-1 rounded bg-indigo-100 text-indigo-700">Aprobar mensaje</button>
                                                    </form>
                                                @endif
                                            </div>
                                        @empty
                                            <p class="text-xs text-gray-500">Sin mensajes generados.</p>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="py-3 text-xs text-gray-500">Actualiza estado/canal en la columna anterior.</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-4 text-gray-500">No hay prospectos para este run.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
