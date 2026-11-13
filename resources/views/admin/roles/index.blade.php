<x-layouts.app title="Roles & permissions" description="Choose what each role can do in NutriMind.">
    <x-page-header
        eyebrow="People"
        title="Roles & permissions"
        description="Choose what teachers, parents and learners can do. Administrators always have every permission."
        :breadcrumbs="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Roles & permissions']]"
    >
        <x-slot:meta>
            @if ($isDefault)
                <x-badge color="leaf" icon="circle-check">Using the defaults</x-badge>
            @else
                <x-badge color="sky" icon="sliders-horizontal">Customized</x-badge>
            @endif
        </x-slot:meta>
        <x-slot:actions>
            <x-confirm-dialog
                :action="route('admin.roles.update')"
                method="PUT"
                title="Reset every permission to the defaults?"
                message="Each role gets its default permissions back, and your custom changes are lost."
                confirm="Reset permissions"
                button="Reset to defaults"
                button-variant="secondary"
                button-size="md"
                button-icon="rotate-ccw"
                icon="rotate-ccw"
            >
                <x-slot:fields><input type="hidden" name="reset" value="1"></x-slot:fields>
            </x-confirm-dialog>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card
            :label="\App\Enums\UserRole::Admin->label()"
            :value="count(\App\Enums\Permission::cases()).' / '.count(\App\Enums\Permission::cases())"
            :icon="\App\Enums\UserRole::Admin->icon()"
            :color="\App\Enums\UserRole::Admin->color()"
            hint="Always every permission"
            data-role-summary="admin"
            data-animate
            style="--stagger: 1"
        />
        @foreach ($roleSummaries as $summary)
            <x-stat-card
                :label="$summary['role']->label()"
                :value="$summary['granted'].' / '.$summary['applicable']"
                :icon="$summary['role']->icon()"
                :color="$summary['role']->color()"
                :hint="$summary['changed'] === 0 ? 'Default permissions' : trans_choice(':count change from the defaults|:count changes from the defaults', $summary['changed'])"
                data-role-summary="{{ $summary['role']->value }}"
                data-animate
                style="--stagger: {{ $loop->iteration + 1 }}"
            />
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.roles.update') }}" class="flex flex-col gap-6" data-submit-once data-setup-roles-form>
        @csrf
        @method('PUT')

        @error('permissions')
            <x-alert variant="danger" title="The permissions could not be saved">{{ $message }}</x-alert>
        @enderror
        @foreach (\App\Services\RolePermissions::configurableRoles() as $configurableRole)
            @error('permissions.'.$configurableRole->value)
                <x-alert variant="danger" :title="$configurableRole->label().' permissions could not be saved'">{{ $message }}</x-alert>
            @enderror
        @endforeach

        @foreach ($groups as $group)
            <x-section :title="$group['label']" padding="none" data-permission-group="{{ \Illuminate\Support\Str::slug($group['label']) }}">
                <x-table flush :caption="$group['label'].' permissions'">
                    <x-slot:head>
                        <x-table.th>Permission</x-table.th>
                        @foreach ($roles as $role)
                            <x-table.th align="center" class="w-32">{{ $role->label() }}</x-table.th>
                        @endforeach
                    </x-slot:head>

                    @foreach ($group['permissions'] as $permission)
                        @php
                            $isCustomized = collect($permission->roles())->contains(
                                fn (\App\Enums\UserRole $role): bool => in_array($permission->value, $matrix[$role->value] ?? [], true) !== in_array($role, $permission->defaultRoles(), true)
                            );
                        @endphp
                        <x-table.row data-permission-row="{{ $permission->value }}">
                            <x-table.td class="min-w-64">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-ink-900 dark:text-ink-50">{{ $permission->label() }}</span>
                                    @if ($isCustomized)
                                        <x-badge color="sky" size="sm" dot>Changed</x-badge>
                                    @endif
                                </div>
                                <p class="mt-0.5 max-w-xl text-xs text-ink-500 dark:text-ink-400">{{ $permission->description() }}</p>
                                @if ($permission->enforcedByGame())
                                    <p class="mt-1.5 inline-flex items-center gap-1.5 text-xs font-medium text-grape-700 dark:text-grape-300">
                                        <x-icon name="gamepad-2" class="size-3.5" />
                                        Enforced by the NutriMind game
                                    </p>
                                @endif
                            </x-table.td>

                            @foreach ($roles as $role)
                                <x-table.td align="center" data-permission-cell="{{ $role->value }}">
                                    @if ($role === \App\Enums\UserRole::Admin)
                                        <span class="relative inline-flex size-8 items-center justify-center rounded-lg bg-grape-50 text-grape-700 ring-1 ring-grape-600/15 ring-inset dark:bg-grape-400/10 dark:text-grape-300 dark:ring-grape-400/20" title="Administrators always have every permission" data-permission-locked>
                                            <x-icon name="check" class="size-4" stroke-width="2.5" />
                                            <span class="absolute -right-1 -bottom-1 grid size-4 place-items-center rounded-full bg-white text-grape-700 ring-1 ring-grape-600/20 dark:bg-ink-900 dark:text-grape-300 dark:ring-grape-400/25">
                                                <x-icon name="lock" class="size-2.5" stroke-width="2.5" />
                                            </span>
                                            <span class="sr-only">Always granted to administrators</span>
                                        </span>
                                    @elseif (in_array($role, $permission->roles(), true))
                                        <div class="inline-flex justify-center">
                                            <x-form.checkbox
                                                :name="'permissions['.$role->value.'][]'"
                                                :value="$permission->value"
                                                :checked="in_array($permission->value, $matrix[$role->value] ?? [], true)"
                                                :aria-label="'Allow '.$role->label().' to '.lcfirst($permission->label())"
                                            />
                                        </div>
                                    @else
                                        <span class="text-ink-300 dark:text-ink-600" aria-hidden="true">—</span>
                                        <span class="sr-only">Not available to {{ $role->label() }}</span>
                                    @endif
                                </x-table.td>
                            @endforeach
                        </x-table.row>
                    @endforeach
                </x-table>
            </x-section>
        @endforeach

        <x-form.actions submit="Save permissions" submit-icon="shield-check">
            <x-icon name="info" class="size-4" />
            <span data-setup-roles-changes data-none="Changes apply to every account of that role as soon as you save." aria-live="polite">Changes apply to every account of that role as soon as you save.</span>
        </x-form.actions>
    </form>
</x-layouts.app>
