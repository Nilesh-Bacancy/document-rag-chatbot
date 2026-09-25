<script setup>
import { nextTick, ref, watch } from 'vue';
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

const messagesEl = ref(null);

// The question being answered right now. It's shown straight away as a chat
// bubble (with a typing indicator under it) so the user can see their message
// was sent, instead of staring at a disabled input until the answer arrives.
const pendingQuestion = ref(null);

// The last question that failed, so it can be retried with one click.
const failedQuestion = ref(null);

function scrollToBottom() {
    nextTick(() => {
        if (messagesEl.value) {
            messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
        }
    });
}

watch(() => props.messages.length, scrollToBottom, { immediate: true });

function send(question) {
    pendingQuestion.value = question;
    failedQuestion.value = null;
    form.question = question;
    scrollToBottom();

    form.post(`/documents/${props.document.id}/chat`, {
        preserveScroll: true,
        onStart: () => {
            // Clear the input right away, like ChatGPT; the question now
            // lives in the pending bubble. (The request data is already
            // captured by this point, so this doesn't affect what's sent.)
            form.question = '';
        },
        onError: () => {
            failedQuestion.value = question;
        },
        onFinish: () => {
            pendingQuestion.value = null;
            scrollToBottom();
        },
    });
}

function submit() {
    const question = form.question.trim();

    if (!question || form.processing) {
        return;
    }

    send(question);
}

function retry() {
    if (failedQuestion.value && !form.processing) {
        send(failedQuestion.value);
    }
}
</script>

<template>
    <div class="mx-auto flex h-screen max-w-3xl flex-col p-6">
        <div class="mb-4 shrink-0">
            <Link href="/documents" class="text-sm text-gray-500 hover:underline">&larr; Documents</Link>
            <h1 class="mt-1 text-xl font-semibold text-gray-900">{{ document.original_filename }}</h1>
        </div>

        <div ref="messagesEl" class="min-h-0 flex-1 space-y-4 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-4">
            <p v-if="messages.length === 0 && !pendingQuestion && !failedQuestion" class="text-center text-sm text-gray-400">
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

            <template v-if="pendingQuestion || failedQuestion">
                <div class="flex justify-end">
                    <div
                        class="max-w-[80%] rounded-lg bg-gray-900 px-4 py-2 text-sm whitespace-pre-wrap text-white"
                        :class="{ 'opacity-60': failedQuestion && !pendingQuestion }"
                    >
                        {{ pendingQuestion || failedQuestion }}
                    </div>
                </div>

                <div v-if="pendingQuestion" class="flex justify-start">
                    <div class="flex items-center gap-1 rounded-lg bg-white px-4 py-3 shadow-sm" aria-label="Assistant is typing">
                        <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400 [animation-delay:-0.3s]"></span>
                        <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400 [animation-delay:-0.15s]"></span>
                        <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400"></span>
                    </div>
                </div>

                <div v-else-if="form.errors.question" class="flex justify-start">
                    <div class="max-w-[80%] rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
                        {{ form.errors.question }}
                        <button type="button" class="ml-2 font-medium underline hover:no-underline" @click="retry">Retry</button>
                    </div>
                </div>
            </template>
        </div>

        <p v-if="form.errors.question && !failedQuestion" class="mt-2 shrink-0 text-sm text-red-600">{{ form.errors.question }}</p>

        <form @submit.prevent="submit" class="mt-4 flex shrink-0 gap-2">
            <input
                v-model="form.question"
                type="text"
                placeholder="Ask a question..."
                class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-gray-900"
            />
            <button
                type="submit"
                class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                :disabled="form.processing || !form.question.trim()"
            >
                Send
            </button>
        </form>
    </div>
</template>
