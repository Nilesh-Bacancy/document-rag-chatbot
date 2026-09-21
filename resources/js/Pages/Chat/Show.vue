<script setup>
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    document: {
        type: Object,
        required: true,
    },
    messages: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    question: '',
});

function submit() {
    if (!form.question.trim()) {
        return;
    }

    form.post(`/documents/${props.document.id}/chat`, {
        preserveScroll: true,
        onSuccess: () => form.reset('question'),
    });
}
</script>

<template>
    <div class="mx-auto flex max-w-3xl flex-col p-6" style="min-height: 100vh">
        <div class="mb-4">
            <Link href="/documents" class="text-sm text-gray-500 hover:underline">&larr; Documents</Link>
            <h1 class="mt-1 text-xl font-semibold text-gray-900">{{ document.original_filename }}</h1>
        </div>

        <div class="flex-1 space-y-4 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-4">
            <p v-if="messages.length === 0" class="text-center text-sm text-gray-400">
                Ask a question about this document to get started.
            </p>

            <div v-for="message in messages" :key="message.id" class="flex" :class="message.role === 'user' ? 'justify-end' : 'justify-start'">
                <div
                    class="max-w-[80%] rounded-lg px-4 py-2 text-sm whitespace-pre-wrap"
                    :class="message.role === 'user' ? 'bg-gray-900 text-white' : 'bg-white text-gray-900 shadow-sm'"
                >
                    {{ message.content }}
                </div>
            </div>
        </div>

        <p v-if="form.errors.question" class="mt-2 text-sm text-red-600">{{ form.errors.question }}</p>

        <form @submit.prevent="submit" class="mt-4 flex gap-2">
            <input
                v-model="form.question"
                type="text"
                placeholder="Ask a question..."
                class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-gray-900"
                :disabled="form.processing"
            />
            <button
                type="submit"
                class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                :disabled="form.processing || !form.question.trim()"
            >
                {{ form.processing ? 'Thinking…' : 'Send' }}
            </button>
        </form>
    </div>
</template>
