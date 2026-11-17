<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { exactUsd, formatUsd } from '@/lib/money';
import { index, store } from '@/routes/credits';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Credits', href: index() }] },
});

defineProps<{
    packs: number[];
    transactions: {
        id: number;
        type: 'purchase' | 'hold' | 'release' | 'charge';
        amount_nanos: number;
        prompt: string | null;
        created_at: string;
    }[];
}>();

const page = usePage();
const balance = computed(() => page.props.auth.user.balance_nanos);
const buying = ref<number | null>(null);

const typeLabel = {
    purchase: 'Bought credits',
    hold: 'Set aside (max price)',
    release: 'Returned hold',
    charge: 'Charged',
};

function buy(dollars: number) {
    buying.value = dollars;
    router.post(
        store.url(),
        { dollars },
        { preserveScroll: true, onFinish: () => (buying.value = null) },
    );
}
</script>

<template>
    <Head title="Credits" />

    <div class="mx-auto w-full max-w-3xl space-y-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">Credits</h1>
            <p class="text-sm text-muted-foreground">
                1 credit = $1. This is a demo, so purchases are free and no
                payment is taken.
            </p>
        </div>

        <p
            v-if="page.props.flash.success"
            class="rounded-md border bg-muted px-3 py-2 text-sm"
        >
            {{ page.props.flash.success }}
        </p>

        <div
            class="flex flex-wrap items-end justify-between gap-4 rounded-xl border p-4"
        >
            <div>
                <p class="text-sm text-muted-foreground">Balance</p>
                <p class="text-3xl font-semibold" :title="exactUsd(balance)">
                    {{ formatUsd(balance) }}
                </p>
            </div>
            <div class="flex gap-2">
                <Button
                    v-for="dollars in packs"
                    :key="dollars"
                    :disabled="buying !== null"
                    @click="buy(dollars)"
                >
                    {{ buying === dollars ? 'Adding…' : `Buy $${dollars}` }}
                </Button>
            </div>
        </div>

        <div>
            <h2 class="mb-2 font-medium">Recent activity</h2>
            <p
                v-if="transactions.length === 0"
                class="text-sm text-muted-foreground"
            >
                No activity yet.
            </p>
            <table v-else class="w-full text-sm">
                <tbody>
                    <tr v-for="t in transactions" :key="t.id" class="border-t">
                        <td
                            class="py-2 pr-2 whitespace-nowrap text-muted-foreground"
                        >
                            {{ new Date(t.created_at).toLocaleString() }}
                        </td>
                        <td class="py-2 pr-2">
                            {{ typeLabel[t.type] }}
                            <span
                                v-if="t.prompt"
                                class="block max-w-xs truncate text-xs text-muted-foreground"
                                >{{ t.prompt }}</span
                            >
                        </td>
                        <td
                            class="py-2 text-right font-mono"
                            :class="
                                t.amount_nanos < 0
                                    ? 'text-destructive'
                                    : 'text-green-600'
                            "
                            :title="exactUsd(t.amount_nanos)"
                        >
                            {{ t.amount_nanos > 0 ? '+' : ''
                            }}{{ formatUsd(t.amount_nanos) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
