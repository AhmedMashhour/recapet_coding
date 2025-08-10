<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <div class="flex gap-4">
            <x-filament::button
                wire:click="runAudit"
                wire:loading.attr="disabled"
                icon="heroicon-o-play"
                size="lg"
            >
                <span wire:loading.remove wire:target="runAudit">Run Audit</span>
                <span wire:loading wire:target="runAudit">Running Audit...</span>
            </x-filament::button>

            @if($this->auditResults)
                <x-filament::button
                    wire:click="downloadReport"
                    icon="heroicon-o-arrow-down-tray"
                    color="gray"
                    size="lg"
                >
                    Download Report
                </x-filament::button>
            @endif
        </div>

        @if($this->auditResults)
            <div class="space-y-6">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-filament::card>
                        <div class="text-center">
                            <div class="text-3xl font-bold {{ $this->auditResults['summary']['total_errors'] > 0 ? 'text-danger-600' : 'text-success-600' }}">
                                {{ $this->auditResults['summary']['total_errors'] }}
                                <!-- Debug Info (remove in production) -->
                                @if(config('app.debug'))
                                    <x-filament::section collapsible collapsed>
                                        <x-slot name="heading">
                                            Debug Information
                                        </x-slot>

                                        <div class="space-y-2 text-sm">
                                            <div>Audit Results Loaded: {{ $this->auditResults ? 'Yes' : 'No' }}</div>
                                            <div>Last Audit File: {{ $this->lastAuditFile ?? 'None' }}</div>
                                            @if($this->auditResults)
                                                <div>Total Errors: {{ $this->auditResults['summary']['total_errors'] ?? 'N/A' }}</div>
                                                <div>Total Warnings: {{ $this->auditResults['summary']['total_warnings'] ?? 'N/A' }}</div>
                                                <div>Errors Array Count: {{ isset($this->auditResults['errors']) ? count($this->auditResults['errors']) : 0 }}</div>
                                                <div>Warnings Array Count: {{ isset($this->auditResults['warnings']) ? count($this->auditResults['warnings']) : 0 }}</div>
                                            @endif
                                        </div>
                                    </x-filament::section>
                                @endif
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Total Errors</div>
                        </div>
                    </x-filament::card>

                    <x-filament::card>
                        <div class="text-center">
                            <div class="text-3xl font-bold {{ $this->auditResults['summary']['total_warnings'] > 0 ? 'text-warning-600' : 'text-success-600' }}">
                                {{ $this->auditResults['summary']['total_warnings'] }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Total Warnings</div>
                        </div>
                    </x-filament::card>

                    <x-filament::card>
                        <div class="text-center">
                            <div class="text-3xl font-bold {{ $this->auditResults['summary']['total_discrepancy'] > 0 ? 'text-danger-600' : 'text-success-600' }}">
                                ${{ number_format($this->auditResults['summary']['total_discrepancy'], 2) }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Total Discrepancy</div>
                        </div>
                    </x-filament::card>
                </div>

                <!-- Last Audit Info -->
                <x-filament::section>
                    <x-slot name="heading">
                        Audit Information
                    </x-slot>

                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Last audit performed on: <span class="font-medium">{{ $this->formattedAuditDate }}</span>
                    </div>
                </x-filament::section>

                <!-- Errors by Type -->
                @if(count($this->errorsByType) > 0)
                    <x-filament::section collapsible>
                        <x-slot name="heading">
                            Errors by Type
                        </x-slot>

                        <div class="space-y-4">
                            @foreach($this->errorsByType as $typeGroup)
                                <div class="border border-danger-200 dark:border-danger-800 rounded-lg p-4">
                                    <div class="flex justify-between items-center mb-2">
                                        <h4 class="text-lg font-medium text-danger-600 dark:text-danger-400">
                                            {{ $typeGroup['type'] }}
                                        </h4>
                                        <div class="flex gap-4 text-sm">
                                            <span class="text-gray-600 dark:text-gray-400">
                                                Count: <span class="font-medium">{{ $typeGroup['count'] }}</span>
                                            </span>
                                            @if($typeGroup['total_amount'] > 0)
                                                <span class="text-gray-600 dark:text-gray-400">
                                                    Amount: <span class="font-medium">${{ number_format($typeGroup['total_amount'], 2) }}</span>
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    @if($this->data['detailed'])
                                        <div class="mt-3 space-y-2">
                                            @foreach(array_slice($typeGroup['errors'], 0, 5) as $error)
                                                <div class="text-sm bg-danger-50 dark:bg-danger-900/20 p-2 rounded">
                                                    <pre class="whitespace-pre-wrap">{{ json_encode($error, JSON_PRETTY_PRINT) }}</pre>
                                                </div>
                                            @endforeach
                                            @if(count($typeGroup['errors']) > 5)
                                                <div class="text-sm text-gray-500 dark:text-gray-400 italic">
                                                    ... and {{ count($typeGroup['errors']) - 5 }} more
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif

                <!-- Warnings -->
                @if(count($this->warningsByType) > 0)
                    <x-filament::section collapsible collapsed>
                        <x-slot name="heading">
                            Warnings
                        </x-slot>

                        <div class="space-y-4">
                            @foreach($this->warningsByType as $type => $warnings)
                                <div class="border border-warning-200 dark:border-warning-800 rounded-lg p-4">
                                    <h4 class="text-lg font-medium text-warning-600 dark:text-warning-400 mb-2">
                                        {{ $this->formatErrorType($type) }}
                                    </h4>

                                    @if($this->data['detailed'])
                                        <div class="space-y-2">
                                            @foreach($warnings as $warning)
                                                <div class="text-sm bg-warning-50 dark:bg-warning-900/20 p-2 rounded">
                                                    <pre class="whitespace-pre-wrap">{{ json_encode($warning, JSON_PRETTY_PRINT) }}</pre>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ count($warnings) }} warning(s) found
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                @endif
            </div>
        @else
            <x-filament::section>
                <div class="text-center py-8">
                    <x-heroicon-o-document-magnifying-glass class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600" />
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No audit results</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Run an audit to see the financial health of your system.</p>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
