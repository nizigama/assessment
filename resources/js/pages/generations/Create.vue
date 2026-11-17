<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { exactUsd, formatUsd } from '@/lib/money';
import { estimatePrice } from '@/lib/pricing';
import type { Pricing } from '@/lib/pricing';
import { index as credits } from '@/routes/credits';
import { create, store } from '@/routes/generations';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Generate', href: create() }] },
});

const props = defineProps<{
    pricing: Pricing;
    sizes: string[];
    qualities: string[];
}>();

const sizeLabels: Record<string, string> = {
    '1024x1024': 'Square (1024 × 1024)',
    '1536x1024': 'Landscape (1536 × 1024)',
    '1024x1536': 'Portrait (1024 × 1536)',
};

const page = usePage();
const balance = computed(() => page.props.auth.user.balance_nanos);

const generateForm = useForm({
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    reference_images: [] as string[],
});

const price = computed(() =>
    estimatePrice(
        generateForm.prompt,
        generateForm.size,
        generateForm.quality,
        generateForm.reference_images.length,
        props.pricing,
    ),
);
const canAfford = computed(() => balance.value >= price.value.max);
const canAddReference = computed(
    () => generateForm.reference_images.length < 4,
);

function addReference() {
    generateForm.reference_images.push('');
}

function setReference(i: number, url: string | number) {
    generateForm.reference_images[i] = String(url);
}

function removeReference(i: number) {
    generateForm.reference_images.splice(i, 1);
}

/** Errors the server can return for keys that aren't form fields (e.g. "credits", "reference_images.2"). */
function serverError(key: string): string | undefined {
    return (generateForm.errors as Record<string, string | undefined>)[key];
}

/** The first problem with the reference URLs, whether it's about the list or one URL. */
function referenceError(): string | undefined {
    const keys = Object.keys(generateForm.errors).filter((key) =>
        key.startsWith('reference_images'),
    );

    return keys.length > 0 ? serverError(keys[0]) : undefined;
}

function submit() {
    generateForm.post(store.url(), {
        onSuccess: () => generateForm.reset('prompt', 'reference_images'),
    });
}
</script>

<template>
    <Head title="Generate" />

    <div
        class="mx-auto grid w-full max-w-5xl gap-6 p-4 lg:grid-cols-[1fr_320px]"
    >
        <form class="space-y-6" @submit.prevent="submit">
            <div>
                <h1 class="text-xl font-semibold">Generate an image</h1>
                <p class="text-sm text-muted-foreground">
                    Describe what you want. Images are ready in about 10 to 40
                    seconds.
                </p>
            </div>

            <div class="grid gap-2">
                <div class="flex items-baseline justify-between">
                    <Label for="prompt">Prompt</Label>
                    <span
                        class="text-xs text-muted-foreground"
                        :class="{
                            'text-destructive':
                                generateForm.prompt.length > 4000,
                        }"
                    >
                        {{ generateForm.prompt.length }} / 4000
                    </span>
                </div>
                <textarea
                    id="prompt"
                    v-model="generateForm.prompt"
                    rows="5"
                    required
                    placeholder="A lighthouse on a cliff at dusk, oil painting"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError :message="generateForm.errors.prompt" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="size">Size</Label>
                    <select
                        id="size"
                        v-model="generateForm.size"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                    >
                        <option v-for="size in sizes" :key="size" :value="size">
                            {{ sizeLabels[size] ?? size }}
                        </option>
                    </select>
                    <InputError :message="generateForm.errors.size" />
                </div>
                <div class="grid gap-2">
                    <Label for="quality">Quality</Label>
                    <select
                        id="quality"
                        v-model="generateForm.quality"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm capitalize shadow-xs"
                    >
                        <option
                            v-for="quality in qualities"
                            :key="quality"
                            :value="quality"
                        >
                            {{ quality }}
                        </option>
                    </select>
                    <InputError :message="generateForm.errors.quality" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label
                    >Reference images
                    <span class="font-normal text-muted-foreground"
                        >(optional, up to 4 URLs)</span
                    ></Label
                >
                <div
                    v-for="(url, i) in generateForm.reference_images"
                    :key="i"
                    class="flex gap-2"
                >
                    <Input
                        :model-value="url"
                        type="url"
                        @update:model-value="setReference(i, $event)"
                        placeholder="Image URL, e.g. example.com/photo.jpg"
                        required
                    />
                    <Button
                        type="button"
                        variant="outline"
                        @click="removeReference(i)"
                        >Remove</Button
                    >
                </div>
                <InputError :message="referenceError()" />
                <div>
                    <Button
                        v-if="canAddReference"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addReference"
                    >
                        Add reference image
                    </Button>
                </div>
            </div>

            <InputError :message="serverError('credits')" />

            <Button
                type="submit"
                :disabled="
                    generateForm.processing ||
                    !canAfford ||
                    generateForm.prompt.trim() === ''
                "
            >
                {{
                    generateForm.processing
                        ? 'Sending…'
                        : `Generate · about ${formatUsd(price.estimated)}`
                }}
            </Button>
        </form>

        <aside class="h-fit space-y-4 rounded-xl border p-4">
            <div>
                <p class="text-sm text-muted-foreground">Estimated cost</p>
                <p
                    class="text-3xl font-semibold"
                    :title="exactUsd(price.estimated)"
                >
                    {{ formatUsd(price.estimated) }}
                </p>
                <p class="text-sm text-muted-foreground">
                    at most
                    <span :title="exactUsd(price.max)">{{
                        formatUsd(price.max)
                    }}</span>
                </p>
            </div>
            <p class="text-xs text-muted-foreground">
                The final price depends on how much the model has to "think",
                which is only known once the image is done. We set aside the
                maximum while it runs, then charge the exact amount and return
                the rest. Failed generations are free.
            </p>
            <div class="border-t pt-4">
                <p class="text-sm text-muted-foreground">Your balance</p>
                <p class="text-xl font-semibold" :title="exactUsd(balance)">
                    {{ formatUsd(balance) }}
                </p>
                <p v-if="!canAfford" class="mt-2 text-sm text-destructive">
                    Not enough credits for this request.
                    <Link :href="credits()" class="underline">Buy credits</Link>
                </p>
            </div>
        </aside>
    </div>
</template>
