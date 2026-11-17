<script setup lang="ts">
import { Head, Link, usePage, usePoll } from '@inertiajs/vue3';
import { watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import { exactUsd, formatUsd } from '@/lib/money';
import { create, index } from '@/routes/generations';
import type { Generation, GenerationStatus } from '@/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'History', href: index() }] },
});

const props = defineProps<{
    generations: {
        data: Generation[];
        links: { prev: string | null; next: string | null };
        meta: { current_page: number; last_page: number };
    };
    hasActive: boolean;
}>();

const page = usePage();

// Refresh every 3 seconds, but only while something is still in progress.
const { start, stop } = usePoll(
    3000,
    { only: ['generations', 'hasActive', 'auth'] },
    { autoStart: props.hasActive },
);
watch(
    () => props.hasActive,
    (active) => (active ? start() : stop()),
);

const statusLabel: Record<GenerationStatus, string> = {
    pending: 'Sending to Pixelforge',
    queued: 'Queued',
    processing: 'Generating',
    succeeded: 'Done',
    failed: 'Failed',
};

const statusVariant: Record<
    GenerationStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'outline',
    queued: 'outline',
    processing: 'secondary',
    succeeded: 'default',
    failed: 'destructive',
};

function isActive(g: Generation): boolean {
    return (
        g.status === 'pending' ||
        g.status === 'queued' ||
        g.status === 'processing'
    );
}
</script>

<template>
    <Head title="History" />

    <div class="mx-auto w-full max-w-5xl space-y-4 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">History</h1>
                <p class="text-sm text-muted-foreground">
                    Your generations, newest first. This page updates live.
                </p>
            </div>
            <Link :href="create()" class="text-sm underline"
                >New generation</Link
            >
        </div>

        <p
            v-if="page.props.flash.success"
            class="rounded-md border bg-muted px-3 py-2 text-sm"
        >
            {{ page.props.flash.success }}
        </p>

        <p
            v-if="generations.data.length === 0"
            class="py-12 text-center text-muted-foreground"
        >
            Nothing here yet.
            <Link :href="create()" class="underline"
                >Generate your first image</Link
            >.
        </p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <article
                v-for="g in generations.data"
                :key="g.id"
                class="overflow-hidden rounded-xl border"
            >
                <div
                    class="flex aspect-square items-center justify-center bg-muted"
                >
                    <a
                        v-if="g.image_url"
                        :href="g.image_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="h-full w-full"
                    >
                        <img
                            :src="g.image_url"
                            :alt="g.prompt"
                            class="h-full w-full object-contain"
                            loading="lazy"
                        />
                    </a>
                    <div
                        v-else-if="isActive(g)"
                        class="flex flex-col items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Spinner />
                        {{ statusLabel[g.status] }}…
                    </div>
                    <p
                        v-else-if="g.image_expired"
                        class="px-4 text-center text-sm text-muted-foreground"
                    >
                        Image link expired (Pixelforge keeps images for 24
                        hours).
                    </p>
                    <p
                        v-else-if="g.status === 'failed'"
                        class="px-4 text-center text-sm text-destructive"
                    >
                        {{ g.error_message }}
                    </p>
                </div>

                <div class="space-y-2 p-3 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <Badge :variant="statusVariant[g.status]">{{
                            statusLabel[g.status]
                        }}</Badge>
                        <span class="text-xs text-muted-foreground">{{
                            new Date(g.created_at).toLocaleString()
                        }}</span>
                    </div>

                    <p class="line-clamp-2" :title="g.prompt">{{ g.prompt }}</p>
                    <p class="text-xs text-muted-foreground capitalize">
                        {{ g.size }} · {{ g.quality
                        }}<template v-if="g.reference_images.length">
                            · {{ g.reference_images.length }} reference
                            image(s)</template
                        >
                    </p>

                    <dl class="grid grid-cols-2 gap-x-2 border-t pt-2 text-xs">
                        <dt class="text-muted-foreground">Estimated</dt>
                        <dd
                            class="text-right"
                            :title="`${exactUsd(g.estimated_price_nanos)}, at most ${exactUsd(g.max_price_nanos)}`"
                        >
                            {{ formatUsd(g.estimated_price_nanos) }}
                        </dd>
                        <dt class="text-muted-foreground">Actual</dt>
                        <dd class="text-right font-medium">
                            <span
                                v-if="g.price_nanos !== null"
                                :title="exactUsd(g.price_nanos)"
                            >
                                {{
                                    g.status === 'failed'
                                        ? 'Free (failed)'
                                        : formatUsd(g.price_nanos)
                                }}
                            </span>
                            <span v-else class="text-muted-foreground"
                                >when finished</span
                            >
                        </dd>
                    </dl>
                    <p v-if="g.tokens" class="text-xs text-muted-foreground">
                        Tokens: {{ g.tokens.input_text }} text ·
                        {{ g.tokens.input_image }} image ·
                        {{ g.tokens.output }} output ·
                        {{ g.tokens.reasoning }} reasoning
                    </p>
                </div>
            </article>
        </div>

        <nav
            v-if="generations.meta.last_page > 1"
            class="flex justify-between text-sm"
        >
            <Link
                v-if="generations.links.prev"
                :href="generations.links.prev"
                class="underline"
                >← Newer</Link
            >
            <span v-else />
            <span class="text-muted-foreground"
                >Page {{ generations.meta.current_page }} of
                {{ generations.meta.last_page }}</span
            >
            <Link
                v-if="generations.links.next"
                :href="generations.links.next"
                class="underline"
                >Older →</Link
            >
            <span v-else />
        </nav>
    </div>
</template>
