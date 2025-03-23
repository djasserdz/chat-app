<script setup>
import { ref, onMounted, nextTick } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Echo from 'laravel-echo';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
  conversation: Object,
  messages: Array,
});

const chatMessages = ref([...props.messages]); // Store messages reactively
const form = useForm({ content: '' });

onMounted(() => {
  if (!window.Echo) {
    window.Echo = new Echo({
      broadcaster: 'reverb',
      key: import.meta.env.VITE_REVERB_APP_KEY,
      wsHost: import.meta.env.VITE_REVERB_HOST,
      wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
      wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
      forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
      enabledTransports: ['ws', 'wss'],
    });
  }

  if (!props.conversation?.id) return;

  // Listen for new messages
  window.Echo.channel(`chat.${props.conversation.id}`)
    .listen('MessageSent', (event) => {
      console.log('MessageSent event received:', event);

      if (event.message) {
        // Ensure new message structure is correctly pushed
        chatMessages.value.push({
          id: event.message.id,
          body: event.message.message, // Fix: event.message.message contains the text
          user_id: event.message.user_id,
          created_at: event.message.created_at,
          user: {
            name: event.message.user?.name || 'Unknown User'
          }
        });

        scrollToBottom();
      }
    });

  scrollToBottom();
});

// Smooth scrolling to bottom
const scrollToBottom = () => {
  nextTick(() => {
    const container = document.getElementById('message-container');
    if (container) {
      container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
    }
  });
};

// Send message function
const sendMessage = () => {
  if (!form.content.trim()) return;
  if (!props.conversation?.id) {
    console.error('Conversation ID is missing');
    return;
  }

  form.post(`/chat/${props.conversation.id}/create`, {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      scrollToBottom();
    },
    onError: (errors) => {
      console.error('Error sending message:', errors);
    }
  });
};
</script>

<template>
  <AuthenticatedLayout>
    <div class="flex flex-col h-screen bg-gray-100">
      <!-- Chat Header -->
      <div class="bg-white p-4 border-b flex items-center space-x-4 shadow-md sticky top-0 z-10">
        <img
          :src="conversation?.users?.[1]?.profile_picture || '/default-avatar.png'"
          alt="User Avatar"
          class="w-12 h-12 rounded-full object-cover border border-gray-300"
        />
        <div>
          <h1 class="text-lg font-semibold text-gray-900">{{ conversation?.name || 'Chat' }}</h1>
          <p class="text-sm text-gray-500">{{ conversation?.users?.length || 0 }} participants</p>
        </div>
      </div>

      <!-- Messages Container -->
      <div id="message-container" class="flex-1 overflow-y-auto p-4 space-y-4">
        <div
          v-for="message in chatMessages"
          :key="message.id"
          class="flex items-end space-x-3"
          :class="message.user_id === $page.props.auth.user.id ? 'justify-end' : 'justify-start'"
        >
          <!-- User Avatar -->
          <img
            v-if="message.user_id !== $page.props.auth.user.id"
            :src="message.user?.profile_picture || '/default-avatar.png'"
            alt="User Avatar"
            class="w-8 h-8 rounded-full object-cover border border-gray-300"
          />

          <!-- Message Bubble -->
          <div
            class="max-w-xs sm:max-w-sm md:max-w-md lg:max-w-lg p-3 rounded-xl shadow-md"
            :class="message.user_id === $page.props.auth.user.id
              ? 'bg-blue-500 text-white self-end rounded-br-none'
              : 'bg-gray-200 text-gray-900 self-start rounded-bl-none'"
          >
            <div class="text-xs font-semibold mb-1" v-if="message.user">
              {{ message.user.name }}
            </div>
            <p class="text-sm leading-tight">{{ message.body }}</p>
            <div class="text-xs mt-1 text-right opacity-70">
              {{ new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Message Input -->
      <div class="border-t p-4 bg-white flex items-center sticky bottom-0">
        <input
          v-model="form.content"
          type="text"
          placeholder="Type a message..."
          class="flex-1 p-3 border rounded-l-full focus:outline-none focus:ring-2 focus:ring-blue-500"
           @keydown.enter.prevent="sendMessage"
        />
        <button
          type="submit"
          @click.prevent="sendMessage"
          class="bg-blue-500 text-white px-6 py-3 rounded-r-full hover:bg-blue-600 transition font-semibold"
          :disabled="form.processing"
        >
          Send
        </button>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
