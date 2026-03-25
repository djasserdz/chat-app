<script setup>
import { ref, onMounted, onUnmounted, nextTick } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { echoReady } from "@/echo";

const props = defineProps({
    conversation: Object,
    messages: Array,
});

const chatMessages = ref([...props.messages]);
const form = useForm({
    content: "",
    file: null,
});
const selectedFileName = ref(null);

onMounted(() => {
    if (!props.conversation?.id) return;

    // ✅ Always leave first to avoid duplicate listeners
    window.Echo?.leave(`chat.${props.conversation.id}`);

    echoReady.then((Echo) => {
        Echo.channel(`chat.${props.conversation.id}`).listen(
            ".MessageSent",
            (event) => {
                if (event.message) {
                    // ✅ Avoid duplicates
                    const exists = chatMessages.value.some(
                        (m) => m.id === event.message.id,
                    );
                    if (!exists) {
                        chatMessages.value.push(event.message);
                        scrollToBottom();
                    }
                }
            },
        );
    });

    scrollToBottom();
});

onUnmounted(() => {
    if (props.conversation?.id) {
        window.Echo?.leave(`chat.${props.conversation.id}`);
    }
});

const scrollToBottom = () => {
    nextTick(() => {
        const container = document.getElementById("message-container");
        container?.scrollTo({
            top: container.scrollHeight,
            behavior: "smooth",
        });
    });
};

const sendMessage = () => {
    if (!form.content.trim() && !form.file) return;

    form.post(`/chat/${props.conversation.id}/create`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            selectedFileName.value = null;
            scrollToBottom();
        },
    });
};

const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.file = file;
        selectedFileName.value = file.name;
    }
};

const removeSelectedFile = () => {
    form.file = null;
    selectedFileName.value = null;
    const fileInput = document.querySelector('input[type="file"]');
    if (fileInput) fileInput.value = "";
};

const isImageFile = (filename) => {
    if (!filename) return false;
    return [".jpg", ".jpeg", ".png", ".gif", ".webp", ".svg", ".bmp"].some(
        (ext) => filename.toLowerCase().endsWith(ext),
    );
};

const isVideoFile = (filename) => {
    if (!filename) return false;
    return [
        ".mp4",
        ".webm",
        ".ogg",
        ".mov",
        ".avi",
        ".flv",
        ".wmv",
        ".mkv",
    ].some((ext) => filename.toLowerCase().endsWith(ext));
};

const getAttachmentType = (attachment) => {
    if (!attachment) return null;
    if (attachment.type) {
        const type = attachment.type.toLowerCase();
        if (type === "image" || type.includes("image")) return "image";
        if (type === "video" || type.includes("video")) return "video";
        if (type === "audio" || type.includes("audio")) return "audio";
        return "document";
    }
    const url = attachment.file_url || "";
    if (isImageFile(url)) return "image";
    if (isVideoFile(url)) return "video";
    return "document";
};

const getOtherUserName = () => {
    if (!props.conversation?.users) return "Chat";
    const me = usePage().props.auth.user.id;
    return props.conversation.users.find((u) => u.id !== me)?.name || "Chat";
};
</script>

<template>
    <AuthenticatedLayout>
        <div class="flex flex-col h-screen bg-gray-100">
            <!-- Header -->
            <div
                class="bg-white p-4 border-b flex items-center space-x-4 shadow-md sticky top-0 z-10"
            >
                <img
                    :src="
                        conversation?.users?.[1]?.profile_picture ||
                        '/default-avatar.png'
                    "
                    alt="User Avatar"
                    class="w-12 h-12 rounded-full object-cover border border-gray-300"
                />
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">
                        {{
                            conversation.type === "group"
                                ? conversation.name
                                : getOtherUserName()
                        }}
                    </h1>
                    <p class="text-sm text-gray-500">
                        {{ conversation?.users?.length || 0 }} participants
                    </p>
                </div>
            </div>

            <!-- Messages -->
            <div
                id="message-container"
                class="flex-1 overflow-y-auto p-4 space-y-4"
            >
                <div
                    v-for="message in chatMessages"
                    :key="message.id"
                    class="flex items-end space-x-3"
                    :class="
                        message.user_id === $page.props.auth.user.id
                            ? 'justify-end'
                            : 'justify-start'
                    "
                >
                    <img
                        v-if="message.user_id !== $page.props.auth.user.id"
                        :src="
                            message.user?.profile_picture ||
                            '/default-avatar.png'
                        "
                        alt="Avatar"
                        class="w-8 h-8 rounded-full object-cover border border-gray-300"
                    />

                    <div
                        class="max-w-lg p-3 rounded-xl shadow-md"
                        :class="
                            message.user_id === $page.props.auth.user.id
                                ? 'bg-blue-500 text-white rounded-br-none'
                                : 'bg-gray-200 text-gray-900 rounded-bl-none'
                        "
                    >
                        <div class="text-xs font-semibold mb-1">
                            {{ message.user?.name }}
                        </div>

                        <p v-if="message.body" class="text-sm">
                            {{ message.body }}
                        </p>

                        <!-- Attachments -->
                        <div
                            v-if="message.attachments?.length"
                            class="mt-2 space-y-2"
                        >
                            <div
                                v-for="attachment in message.attachments"
                                :key="attachment.id"
                            >
                                <img
                                    v-if="
                                        getAttachmentType(attachment) ===
                                        'image'
                                    "
                                    :src="attachment.file_url"
                                    class="max-w-full rounded shadow-sm"
                                    alt="Image"
                                />
                                <video
                                    v-else-if="
                                        getAttachmentType(attachment) ===
                                        'video'
                                    "
                                    controls
                                    preload="metadata"
                                    class="max-w-full rounded"
                                    style="max-height: 300px"
                                >
                                    <source
                                        :src="attachment.file_url"
                                        type="video/mp4"
                                    />
                                </video>
                                <audio
                                    v-else-if="
                                        getAttachmentType(attachment) ===
                                        'audio'
                                    "
                                    controls
                                    class="w-full"
                                >
                                    <source
                                        :src="attachment.file_url"
                                        type="audio/mpeg"
                                    />
                                </audio>
                                <div
                                    v-else
                                    class="flex items-center p-2 bg-gray-100 rounded"
                                >
                                    <span class="text-2xl mr-2">📄</span>
                                    <a
                                        :href="attachment.file_url"
                                        target="_blank"
                                        class="text-blue-600 hover:underline text-sm truncate"
                                    >
                                        {{
                                            attachment.file_url.split("/").pop()
                                        }}
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="text-xs mt-1 text-right opacity-70">
                            {{
                                new Date(message.created_at).toLocaleTimeString(
                                    [],
                                    { hour: "2-digit", minute: "2-digit" },
                                )
                            }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input -->
            <div class="border-t p-4 bg-white flex flex-col space-y-2">
                <div class="flex items-center space-x-2">
                    <input
                        v-model="form.content"
                        type="text"
                        placeholder="Type a message..."
                        class="flex-1 p-3 border rounded-l-full focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @keydown.enter.prevent="sendMessage"
                    />
                    <label
                        class="cursor-pointer bg-gray-200 hover:bg-gray-300 p-3 rounded-full flex items-center justify-center"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 text-gray-700"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"
                            />
                        </svg>
                        <input
                            type="file"
                            class="hidden"
                            @change="handleFileChange"
                            accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt"
                        />
                    </label>
                    <button
                        @click.prevent="sendMessage"
                        class="bg-blue-500 text-white px-6 py-3 rounded-r-full hover:bg-blue-600 transition font-semibold flex items-center"
                        :disabled="form.processing"
                    >
                        <svg
                            v-if="form.processing"
                            class="animate-spin -ml-1 mr-2 h-4 w-4 text-white"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        Send
                    </button>
                </div>

                <div
                    v-if="selectedFileName"
                    class="flex items-center space-x-2 bg-gray-100 p-2 rounded-lg"
                >
                    <span class="text-gray-700 text-sm flex-1 truncate">{{
                        selectedFileName
                    }}</span>
                    <button
                        @click="removeSelectedFile"
                        class="text-red-500 hover:text-red-700"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
