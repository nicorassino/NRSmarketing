<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $campaign->name }}
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
                        <p class="text-sm text-gray-500">Producto</p>
                        <p class="font-medium">{{ $campaign->product?->name ?? '-' }}</p>
                        <p class="text-sm text-gray-500 mt-2">Ubicacion objetivo</p>
                        <p class="font-medium">{{ $campaign->target_location ?: 'No definida' }}</p>
                        <p class="text-sm text-gray-500 mt-2">Nicho objetivo</p>
                        <p class="font-medium">{{ $campaign->target_niche ?: 'No definido' }}</p>
                        <p class="text-sm text-gray-500 mt-2">Objetivo comercial</p>
                        <p class="font-medium">{{ $campaign->objective ?: 'No definido' }}</p>
                        <p class="text-sm text-gray-500 mt-2">Estado</p>
                        <p class="font-medium">{{ $campaign->status_label }}</p>
                    </div>
                    <form method="POST" action="{{ route('campaigns.runs.start', $campaign) }}">
                        @csrf
                        <button class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                            Iniciar nuevo run
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Runs</h3>
                <div class="space-y-4">
                    @forelse ($campaign->runs as $run)
                        <div class="border rounded-md p-4">
                            @php
                                $overrides = (array) ($run->metadata['scout_overrides'] ?? []);
                                $previewLocation = $overrides['location'] ?? ($campaign->target_location ?: 'Argentina');
                                $previewHl = $overrides['hl'] ?? 'es';
                                $previewGl = $overrides['gl'] ?? 'ar';
                                $previewNum = (int) ($overrides['num'] ?? config('agents.scout.max_results_per_search', 20));
                                $previewMaxSearches = (int) ($overrides['max_searches'] ?? config('agents.scout.max_searches', 10));
                                $hasMission = $run->contextFiles->contains(fn($f) => $f->step === '02_scout_mission');
                                $canRunScout = $hasMission && filled($previewLocation) && (filled($campaign->target_niche) || filled($campaign->objective));
                                $serpPreview = [
                                    'engine' => 'google',
                                    'location' => $previewLocation,
                                    'hl' => $previewHl,
                                    'gl' => $previewGl,
                                    'num' => $previewNum,
                                    'max_searches' => $previewMaxSearches,
                                ];
                            @endphp
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-semibold">Run #{{ $run->run_number }}</p>
                                    <p class="text-sm text-gray-500">Estado: {{ $run->status }}</p>
                                    <p class="text-sm text-gray-500">Prospectos: {{ $run->prospects()->count() }}</p>
                                    <div class="mt-2 text-xs">
                                        <p class="{{ $hasMission ? 'text-green-700' : 'text-red-700' }}">
                                            {{ $hasMission ? 'Mission lista (02_scout_mission)' : 'Falta mission (ejecuta Analyst)' }}
                                        </p>
                                        <p class="{{ filled($previewLocation) ? 'text-green-700' : 'text-red-700' }}">
                                            {{ filled($previewLocation) ? 'Ubicacion definida' : 'Falta location en payload' }}
                                        </p>
                                        <p class="{{ (filled($campaign->target_niche) || filled($campaign->objective)) ? 'text-green-700' : 'text-red-700' }}">
                                            {{ (filled($campaign->target_niche) || filled($campaign->objective)) ? 'Targeting definido' : 'Falta target_niche u objective' }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <form method="POST" action="{{ route('runs.scout', $run) }}">
                                        @csrf
                                        <button
                                            class="px-3 py-2 text-xs rounded {{ $canRunScout ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-500 cursor-not-allowed' }}"
                                            {{ $canRunScout ? '' : 'disabled' }}
                                            title="{{ $canRunScout ? 'Ejecutar Scout' : 'Completa precondiciones de targeting antes de buscar' }}"
                                        >Ejecutar Scout</button>
                                    </form>
                                    <form method="POST" action="{{ route('runs.executor', $run) }}">
                                        @csrf
                                        <button class="px-3 py-2 text-xs bg-green-100 text-green-800 rounded">Ejecutar Executor</button>
                                    </form>
                                    <a href="{{ route('runs.inbox', $run) }}" class="px-3 py-2 text-xs bg-indigo-100 text-indigo-800 rounded">
                                        Abrir Inbox
                                    </a>
                                    <a href="{{ route('runs.debug', $run) }}" class="px-3 py-2 text-xs bg-gray-100 text-gray-800 rounded">
                                        Debug Run
                                    </a>
                                </div>
                            </div>

                            <div class="mt-3">
                                <p class="text-sm font-medium mb-2">Preview de payload Scout a SerpAPI</p>
                                <pre class="text-xs bg-gray-50 border rounded p-2 overflow-x-auto">{{ json_encode($serpPreview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                <form method="POST" action="{{ route('runs.scout_payload.update', $run) }}" class="mt-3 grid grid-cols-1 md:grid-cols-5 gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="text" name="location" value="{{ $previewLocation }}" class="border-gray-300 rounded-md text-xs" placeholder="Location">
                                    <input type="text" name="hl" value="{{ $previewHl }}" class="border-gray-300 rounded-md text-xs" placeholder="hl">
                                    <input type="text" name="gl" value="{{ $previewGl }}" class="border-gray-300 rounded-md text-xs" placeholder="gl">
                                    <input type="number" name="num" value="{{ $previewNum }}" min="1" max="100" class="border-gray-300 rounded-md text-xs" placeholder="num">
                                    <input type="number" name="max_searches" value="{{ $previewMaxSearches }}" min="1" max="25" class="border-gray-300 rounded-md text-xs" placeholder="max_searches">
                                    <div class="md:col-span-5">
                                        <button class="px-3 py-2 text-xs bg-gray-800 text-white rounded">Guardar payload Scout</button>
                                    </div>
                                </form>
                            </div>

                            <div class="mt-3">
                                <p class="text-sm font-medium mb-2">Ejecuciones</p>
                                <ul class="text-sm text-gray-700 list-disc ml-5">
                                    @forelse ($run->agentExecutions as $execution)
                                        <li>
                                            {{ $execution->agent_type }} - {{ $execution->status }}
                                            @if ($execution->output_result)
                                                - {{ $execution->output_result }}
                                            @endif
                                        </li>
                                    @empty
                                        <li>Sin ejecuciones registradas.</li>
                                    @endforelse
                                </ul>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Todavia no hay runs.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
