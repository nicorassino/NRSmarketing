<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Debug Run #{{ $run->run_number }} - {{ $run->campaign->name }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <p class="text-sm text-gray-500">Producto</p>
                <p class="font-medium">{{ $run->campaign->product?->name ?? '-' }}</p>
                <p class="text-sm text-gray-500 mt-2">Estado run</p>
                <p class="font-medium">{{ $run->status }}</p>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Search requests enviados a Scout/SerpAPI</h3>
                @php $requests = $searchData['search_requests'] ?? []; @endphp
                @if (empty($requests))
                    <p class="text-sm text-gray-500">No hay search_requests guardados aun.</p>
                @else
                    <div class="space-y-3">
                        @foreach ($requests as $idx => $request)
                            <div class="border rounded p-3">
                                <p class="text-sm font-semibold">Request {{ $idx + 1 }}</p>
                                <p class="text-xs text-gray-700 mt-1"><span class="font-medium">Query:</span> {{ $request['query'] ?? '-' }}</p>
                                <pre class="text-xs bg-gray-50 border rounded p-2 mt-2 overflow-x-auto">{{ json_encode($request['params'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Resumen de resultados Scout</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
                    <div class="border rounded p-3">
                        <p class="text-gray-500">Total raw</p>
                        <p class="font-semibold">{{ $searchData['total_raw_results'] ?? 0 }}</p>
                    </div>
                    <div class="border rounded p-3">
                        <p class="text-gray-500">Total after merge</p>
                        <p class="font-semibold">{{ $searchData['total_after_merge'] ?? 0 }}</p>
                    </div>
                    <div class="border rounded p-3">
                        <p class="text-gray-500">Total scored</p>
                        <p class="font-semibold">{{ $searchData['total_scored'] ?? 0 }}</p>
                    </div>
                    <div class="border rounded p-3">
                        <p class="text-gray-500">Total saved</p>
                        <p class="font-semibold">{{ $searchData['total_saved'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Resultados merged antes de scoring (top 20)</h3>
                @php $mergedResults = array_slice($searchData['merged_results'] ?? [], 0, 20); @endphp
                @if (empty($mergedResults))
                    <p class="text-sm text-gray-500">No hay merged_results guardados.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left border-b">
                                    <th class="py-2 pr-3">Empresa</th>
                                    <th class="py-2 pr-3">Fuentes</th>
                                    <th class="py-2 pr-3">CR</th>
                                    <th class="py-2 pr-3">Email</th>
                                    <th class="py-2 pr-3">Telefono</th>
                                    <th class="py-2">Instagram</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($mergedResults as $item)
                                    <tr class="border-b">
                                        <td class="py-2 pr-3">{{ $item['company_name'] ?? $item['title'] ?? '-' }}</td>
                                        <td class="py-2 pr-3 text-xs text-gray-600">{{ implode(', ', $item['source_list'] ?? []) }}</td>
                                        <td class="py-2 pr-3">{{ $item['contact_richness'] ?? 0 }}</td>
                                        <td class="py-2 pr-3">{{ $item['email'] ?? '-' }}</td>
                                        <td class="py-2 pr-3">{{ $item['phone'] ?? '-' }}</td>
                                        <td class="py-2">{{ $item['instagram'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Resultados (top 20)</h3>
                @php $results = array_slice($searchData['results'] ?? [], 0, 20); @endphp
                @if (empty($results))
                    <p class="text-sm text-gray-500">No hay resultados guardados.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left border-b">
                                    <th class="py-2 pr-3">Empresa</th>
                                    <th class="py-2 pr-3">URL</th>
                                    <th class="py-2 pr-3">CR</th>
                                    <th class="py-2 pr-3">Score</th>
                                    <th class="py-2 pr-3">Email</th>
                                    <th class="py-2">Telefono</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($results as $item)
                                    <tr class="border-b">
                                        <td class="py-2 pr-3">{{ $item['company_name'] ?? $item['title'] ?? '-' }}</td>
                                        <td class="py-2 pr-3 text-xs text-gray-600">{{ $item['url'] ?? '-' }}</td>
                                        <td class="py-2 pr-3">{{ $item['contact_richness'] ?? 0 }}</td>
                                        <td class="py-2 pr-3">{{ $item['score'] ?? '-' }}</td>
                                        <td class="py-2 pr-3">{{ $item['email'] ?? '-' }}</td>
                                        <td class="py-2">{{ $item['phone'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">Execution log (Executor)</h3>
                @if (empty($executionData))
                    <p class="text-sm text-gray-500">No hay execution log para este run.</p>
                @else
                    <pre class="text-xs bg-gray-50 border rounded p-3 overflow-x-auto">{{ json_encode($executionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
