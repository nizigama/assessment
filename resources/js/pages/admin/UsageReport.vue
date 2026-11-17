<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { exactUsd, formatUsd } from '@/lib/money';
import { usage } from '@/routes/admin';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Admin · Usage report', href: usage() }] },
});

type Day = {
    day: string;
    charged_nanos: number;
    provider_cost_nanos: number;
    profit_nanos: number;
    pixelforge_cost_nanos: number | null;
};
type Row = {
    day: string;
    user: { id: number; name: string; email: string };
    generations: number;
    charged_nanos: number;
    provider_cost_nanos: number;
    profit_nanos: number;
};

const props = defineProps<{
    range: { start: string; end: string };
    days: Day[];
    rows: Row[];
    pixelforgeError: string | null;
}>();

const totals = computed(() => ({
    charged: props.days.reduce((sum, d) => sum + d.charged_nanos, 0),
    cost: props.days.reduce((sum, d) => sum + d.provider_cost_nanos, 0),
    profit: props.days.reduce((sum, d) => sum + d.profit_nanos, 0),
}));
</script>

<template>
    <Head title="Usage report" />

    <div class="mx-auto w-full max-w-5xl space-y-8 p-4">
        <div>
            <h1 class="text-xl font-semibold">Usage report</h1>
            <p class="text-sm text-muted-foreground">
                {{ range.start }} to {{ range.end }}, grouped by the UTC day
                each generation finished (the day Pixelforge bills it). Amounts
                are rounded to 4 decimals; hover for the exact figure.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">Charged to users</p>
                <p
                    class="text-2xl font-semibold"
                    :title="exactUsd(totals.charged)"
                >
                    {{ formatUsd(totals.charged) }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">Pixelforge costs</p>
                <p
                    class="text-2xl font-semibold"
                    :title="exactUsd(totals.cost)"
                >
                    {{ formatUsd(totals.cost) }}
                </p>
            </div>
            <div class="rounded-xl border p-4">
                <p class="text-sm text-muted-foreground">Profit</p>
                <p
                    class="text-2xl font-semibold"
                    :title="exactUsd(totals.profit)"
                >
                    {{ formatUsd(totals.profit) }}
                </p>
            </div>
        </div>

        <section>
            <h2 class="mb-2 font-medium">Per day</h2>
            <p v-if="pixelforgeError" class="mb-2 text-sm text-destructive">
                {{ pixelforgeError }}
            </p>
            <table class="w-full text-sm">
                <thead class="text-left text-muted-foreground">
                    <tr>
                        <th class="py-2">Day (UTC)</th>
                        <th class="text-right">Charged</th>
                        <th class="text-right">Our provider cost</th>
                        <th class="text-right">Pixelforge says</th>
                        <th class="text-right">Profit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="days.length === 0">
                        <td
                            colspan="5"
                            class="border-t py-4 text-center text-muted-foreground"
                        >
                            No billed generations in this period.
                        </td>
                    </tr>
                    <tr
                        v-for="d in days"
                        :key="d.day"
                        class="border-t font-mono"
                    >
                        <td class="py-2 font-sans">{{ d.day }}</td>
                        <td
                            class="text-right"
                            :title="exactUsd(d.charged_nanos)"
                        >
                            {{ formatUsd(d.charged_nanos) }}
                        </td>
                        <td class="text-right">
                            {{ exactUsd(d.provider_cost_nanos) }}
                        </td>
                        <td class="text-right">
                            <template v-if="d.pixelforge_cost_nanos !== null">
                                {{ exactUsd(d.pixelforge_cost_nanos) }}
                                <span
                                    v-if="
                                        d.pixelforge_cost_nanos ===
                                        d.provider_cost_nanos
                                    "
                                    class="text-green-600"
                                    title="Matches"
                                    >✓</span
                                >
                                <span
                                    v-else
                                    class="text-destructive"
                                    title="Does not match our records"
                                    >✗</span
                                >
                            </template>
                            <span v-else class="text-muted-foreground"
                                >n/a</span
                            >
                        </td>
                        <td
                            class="text-right"
                            :title="exactUsd(d.profit_nanos)"
                        >
                            {{ formatUsd(d.profit_nanos) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section>
            <h2 class="mb-2 font-medium">Per day and user</h2>
            <table class="w-full text-sm">
                <thead class="text-left text-muted-foreground">
                    <tr>
                        <th class="py-2">Day (UTC)</th>
                        <th>User</th>
                        <th class="text-right">Images</th>
                        <th class="text-right">Charged</th>
                        <th class="text-right">Provider cost</th>
                        <th class="text-right">Profit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="r in rows"
                        :key="r.day + r.user.id"
                        class="border-t"
                    >
                        <td class="py-2">{{ r.day }}</td>
                        <td>
                            {{ r.user.name }}
                            <span class="block text-xs text-muted-foreground">{{
                                r.user.email
                            }}</span>
                        </td>
                        <td class="text-right">{{ r.generations }}</td>
                        <td
                            class="text-right font-mono"
                            :title="exactUsd(r.charged_nanos)"
                        >
                            {{ formatUsd(r.charged_nanos) }}
                        </td>
                        <td
                            class="text-right font-mono"
                            :title="exactUsd(r.provider_cost_nanos)"
                        >
                            {{ formatUsd(r.provider_cost_nanos) }}
                        </td>
                        <td
                            class="text-right font-mono"
                            :title="exactUsd(r.profit_nanos)"
                        >
                            {{ formatUsd(r.profit_nanos) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
