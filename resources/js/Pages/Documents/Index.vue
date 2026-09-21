<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    documents: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    document: null,
});

function submit() {
    form.post('/documents', {
        forceFormData: true,
        onSuccess: () => form.reset(),
    });
}

// While any document is still pending/processing, poll the server every few
// seconds so the status column updates without a manual refresh.
const isProcessingAnything = computed(() =>
    props.documents.some((doc) => doc.status === 'pending' || doc.status === 'processing'),
);

let pollTimer = null;

function poll() {
    if (isProcessingAnything.value) {
        router.reload({ only: ['documents'] });
    }
}

onMounted(() => {
    pollTimer = setInterval(poll, 3000);
});

onBeforeUnmount(() => {
    clearInterval(pollTimer);
});

function statusLabel(status) {
    return {
        pending: 'Pending',
        processing: 'Processing…',
        completed: 'Ready',
        failed: 'Failed',
    }[status] ?? status;
}
</script>

<template>
    <div class="mx-auto max-w-3xl p-6">
        <h1 class="text-2xl font-semibold text-gray-900">Document Chatbot</h1>
        <p class="mt-1 text-sm text-gray-500">
            Upload a PDF, wait for it to finish processing, then open the chat to ask questions about it.
        </p>

        <form @submit.prevent="submit" class="mt-6 flex items-center gap-3">
            <input
                type="file"
                accept="application/pdf"
                class="block flex-1 text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-900 file:px-3 file:py-2 file:text-sm file:text-white"
                @input="form.document = $event.target.files[0]"
            />
            <button
                type="submit"
                class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                :disabled="form.processing || !form.document"
            >
                Upload PDF
            </button>
        </form>
        <p v-if="form.errors.document" class="mt-2 text-sm text-red-600">{{ form.errors.document }}</p>

        <table class="mt-8 w-full text-left text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-gray-500">
                    <th class="py-2 font-medium">File</th>
                    <th class="py-2 font-medium">Status</th>
                    <th class="py-2 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="doc in documents" :key="doc.id" class="border-b border-gray-100">
                    <td class="py-3 text-gray-900">{{ doc.original_filename }}</td>
                    <td class="py-3">
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="{
                                'bg-yellow-100 text-yellow-800': doc.status === 'pending' || doc.status === 'processing',
                                'bg-green-100 text-green-800': doc.status === 'completed',
                                'bg-red-100 text-red-800': doc.status === 'failed',
                            }"
                        >
                            {{ statusLabel(doc.status) }}
                        </span>
                        <span v-if="doc.status === 'failed' && doc.error_message" class="ml-2 text-xs text-gray-500">
                            {{ doc.error_message }}
                        </span>
                    </td>
                    <td class="py-3 text-right">
                        <Link
                            v-if="doc.status === 'completed'"
                            :href="`/documents/${doc.id}/chat`"
                            class="text-sm font-medium text-blue-600 hover:underline"
                        >
                            Open chat
                        </Link>
                    </td>
                </tr>
                <tr v-if="documents.length === 0">
                    <td colspan="3" class="py-6 text-center text-gray-400">No documents uploaded yet.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
