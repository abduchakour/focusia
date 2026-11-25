<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import type { EditableQuestion } from '@/types/quiz';
import { ArrowDown, ArrowUp, Check, Copy, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref } from 'vue';

const props = defineProps<{
    index: number;
    total: number;
    notions: { id: number; name: string }[];
    errors: Record<string, string>;
    locked: boolean;
    autofocus?: boolean;
}>();

const question = defineModel<EditableQuestion>({ required: true });

const emit = defineEmits<{
    remove: [];
    duplicate: [];
    move: [direction: -1 | 1];
    newNotion: [];
}>();

const letters = ['A', 'B', 'C', 'D'];
const textarea = ref<HTMLTextAreaElement | null>(null);

const error = (field: string) => props.errors[`questions.${props.index}.${field}`];
const hasError = computed(() => Object.keys(props.errors).some((k) => k.startsWith(`questions.${props.index}.`)));

function autosize() {
    const el = textarea.value;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = `${el.scrollHeight}px`;
}

function onNotionChange(event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    if (value === '__new__') {
        (event.target as HTMLSelectElement).value = String(question.value.notion_id);
        emit('newNotion');
        return;
    }
    question.value.notion_id = value === '' ? '' : Number(value);
}

onMounted(async () => {
    autosize();
    if (props.autofocus) {
        await nextTick();
        textarea.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        textarea.value?.focus({ preventScroll: true });
    }
});
</script>

<template>
    <article
        :class="[
            'rounded-2xl border bg-card p-4 shadow-sm transition-[border-color,box-shadow] duration-300 sm:p-5',
            hasError ? 'border-destructive/60 shadow-destructive/10' : 'focus-within:border-primary/50 focus-within:shadow-md',
        ]"
    >
        <header class="mb-3 flex flex-wrap items-center gap-2">
            <span class="flex size-8 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground">{{
                index + 1
            }}</span>

            <select
                :value="question.notion_id"
                :disabled="locked"
                :aria-label="`Notion de la question ${index + 1}`"
                :class="[
                    'h-9 max-w-[60%] rounded-full border px-3 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                    question.notion_id === '' ? 'border-dashed text-muted-foreground' : 'border-primary/30 bg-secondary text-secondary-foreground',
                ]"
                @change="onNotionChange"
            >
                <option value="" disabled>Notion évaluée…</option>
                <option v-for="notion in notions" :key="notion.id" :value="notion.id">{{ notion.name }}</option>
                <option value="__new__">＋ Nouvelle notion…</option>
            </select>

            <div v-if="!locked" class="ml-auto flex items-center">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-9"
                    :disabled="index === 0"
                    aria-label="Monter"
                    @click="emit('move', -1)"
                >
                    <ArrowUp />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-9"
                    :disabled="index === total - 1"
                    aria-label="Descendre"
                    @click="emit('move', 1)"
                >
                    <ArrowDown />
                </Button>
                <Button type="button" variant="ghost" size="icon" class="size-9" aria-label="Dupliquer la question" @click="emit('duplicate')">
                    <Copy />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-9 text-muted-foreground hover:text-destructive"
                    :disabled="total === 1"
                    aria-label="Supprimer la question"
                    @click="emit('remove')"
                >
                    <Trash2 />
                </Button>
            </div>
        </header>
        <InputError :message="question.notion_id === '' ? error('notion_id') : undefined" class="mb-2" />

        <textarea
            ref="textarea"
            v-model="question.text"
            :disabled="locked"
            rows="2"
            placeholder="Écrivez la question…"
            class="w-full resize-none rounded-xl border border-input bg-background px-3 py-2.5 text-base leading-snug shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-70"
            @input="autosize"
        />
        <InputError :message="question.text.trim() ? undefined : error('text')" class="mt-1" />

        <p class="mb-2 mt-4 text-xs font-medium uppercase tracking-wide text-muted-foreground">
            Propositions — touchez la lettre de la bonne réponse
        </p>
        <ul class="grid gap-2">
            <li v-for="(_, choiceIndex) in question.choices" :key="choiceIndex" class="flex items-center gap-2">
                <button
                    type="button"
                    :disabled="locked"
                    :aria-pressed="question.correct === choiceIndex"
                    :aria-label="`La bonne réponse est ${letters[choiceIndex]}`"
                    :class="[
                        'press flex size-11 shrink-0 items-center justify-center rounded-xl border-2 text-sm font-bold transition-colors duration-200',
                        question.correct === choiceIndex
                            ? 'border-success bg-success text-success-foreground shadow-md shadow-success/30'
                            : 'border-input text-muted-foreground hover:border-success/60 hover:text-success',
                    ]"
                    @click="question.correct = choiceIndex"
                >
                    <Check v-if="question.correct === choiceIndex" class="animate-pop size-5" />
                    <span v-else>{{ letters[choiceIndex] }}</span>
                </button>
                <input
                    v-model="question.choices[choiceIndex]"
                    :disabled="locked"
                    :placeholder="`Proposition ${letters[choiceIndex]}`"
                    :class="[
                        'h-11 min-w-0 flex-1 rounded-xl border bg-background px-3 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-70',
                        error(`choices.${choiceIndex}`) && !question.choices[choiceIndex].trim()
                            ? 'border-destructive bg-destructive/5'
                            : question.correct === choiceIndex
                              ? 'border-success/50 bg-success/5'
                              : 'border-input',
                    ]"
                />
            </li>
        </ul>
        <div class="mt-2 space-y-1">
            <InputError
                :message="
                    question.choices.some((c) => !c.trim())
                        ? (error('choices') ?? Object.entries(errors).find(([k]) => k.startsWith(`questions.${index}.choices.`))?.[1])
                        : undefined
                "
            />
            <InputError :message="question.correct === null ? error('correct') : undefined" />
        </div>
    </article>
</template>
