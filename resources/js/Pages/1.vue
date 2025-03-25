<script setup>
import { ref, watch, onMounted, onUnmounted } from "vue";
import { router } from "@inertiajs/vue3";
import axios from "axios";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Reactive state variables
const searchUser = ref("");
const users = ref([]);
const conversations = ref([]);
const loading = ref({
  users: false,
  conversations: false,
  groups: false
});
const error = ref(null);

// Notification state
const notifications = ref([]);

const props = defineProps({
  conversation: Array,
});

// New group-related state
const selectedGroupUsers = ref([]);
const groupName = ref("");
const showGroupModal = ref(false);

// Notification methods
const formatTime = (timestamp) => {
  const date = new Date(timestamp);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

const removeNotification = (id) => {
  notifications.value = notifications.value.filter(n => n.id !== id);
};

const handleNotificationClick = (notification) => {
  router.visit(`/chat/${notification.conversation_id}`);
  removeNotification(notification.id);
};

// Setup Echo for real-time notifications
const setupEcho = () => {
  // Ensure Pusher is configured
  window.Pusher = Pusher;

  window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true
  });

  // Listen for new messages
  window.Echo.private(`App.Models.User.${window.userId}`)
    .notification((notification) => {
      const newNotification = {
        id: Date.now(),
        sender: {
          name: notification.sender_name,
          profile_picture: notification.sender_profile_picture
        },
        message: notification.message,
        conversation_id: notification.conversation_id,
        timestamp: new Date()
      };

      // Add to notifications
      notifications.value.push(newNotification);

      // Auto-remove after 5 seconds
      setTimeout(() => {
        removeNotification(newNotification.id);
      }, 5000);
    });
};

// Fetch existing conversations
const fetchConversations = async () => {
  loading.value.conversations = true;
  try {
    const response = await axios.get(route("chats"), {
      headers: {
        'Accept': 'application/json'
      }
    });

    // Log the response to verify structure
    console.log('Conversations response:', response.data);

    // Ensure we're accessing the correct path for conversations
    conversations.value = response.data.conversations || [];
  } catch (err) {
    console.error("Error fetching conversations:", err);
    error.value = err.response?.data?.message || "Failed to load conversations";
    conversations.value = [];
  } finally {
    loading.value.conversations = false;
  }
};

const fetchUsers = async () => {
  // Clear previous error
  error.value = null;

  // Trim and validate search input
  const searchTerm = searchUser.value.trim();
  if (searchTerm.length === 0) {
    users.value = [];
    return;
  }

  // Set loading state
  loading.value.users = true;

  try {
    const response = await axios.get(route("chat.search"), {
      params: { search: searchTerm },
      timeout: 5000
    });

    // Validate response data
    if (response.data && Array.isArray(response.data.users)) {
      users.value = response.data.users;
    } else {
      throw new Error("Invalid response format");
    }
  } catch (err) {
    // Comprehensive error handling
    console.error("Error fetching users:", err);
    error.value = err.response?.data?.message || "Failed to fetch users";
    users.value = [];
  } finally {
    loading.value.users = false;
  }
};

// Debounce the search to reduce unnecessary API calls
const debouncedFetchUsers = (() => {
  let timeoutId;
  return () => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(fetchUsers, 300);
  };
})();

// Watch the input field with debounce
watch(searchUser, debouncedFetchUsers);

// Function to start a conversation with error handling
const startConversation = async (user) => {
  try {
    await router.post(route("create.chat"), { user_id: user.id });
  } catch (err) {
    console.error("Failed to start conversation:", err);
    error.value = "Failed to start conversation";
  }
};

// Function to navigate to an existing conversation
const goToConversation = (conversationId) => {
  router.visit(`/chat/${conversationId}`);
};

// New method to toggle user selection for group
const toggleUserSelection = (user) => {
  const index = selectedGroupUsers.value.findIndex(u => u.id === user.id);
  if (index !== -1) {
    // Remove user if already selected
    selectedGroupUsers.value.splice(index, 1);
  } else {
    // Add user if not selected
    selectedGroupUsers.value.push(user);
  }
};

// Create group method
const createGroup = async () => {
  try {
    await router.post(route("create.group"), {
      name: groupName.value,
      user_ids: selectedGroupUsers.value.map(user => user.id)
    });
  } catch (err) {
    console.error("Failed to create group:", err);
    error.value = err.response?.data?.message || "Failed to create group";
  }
};

// Initial load
onMounted(() => {
  fetchUsers();
  fetchConversations();
  setupEcho();
});

// Cleanup on component unmount
onUnmounted(() => {
  if (window.Echo) {
    window.Echo.disconnect();
  }
});
</script>

<template>
  <AuthenticatedLayout>
    <!-- Notification Popup -->
    <div
      v-if="notifications.length"
      class="fixed top-4 right-4 z-50 space-y-2"
    >
      <div
        v-for="notification in notifications"
        :key="notification.id"
        class="bg-white shadow-lg rounded-lg border p-4 transition-all duration-300 ease-in-out flex items-start"
        :class="[
          'animate-slide-in',
          'border-blue-200',
          'bg-blue-50'
        ]"
      >
        <img
          :src="notification.sender.profile_picture || '/default-avatar.png'"
          :alt="`${notification.sender.name}'s avatar`"
          class="w-10 h-10 rounded-full mr-3"
        />

        <div class="flex-1">
          <div class="flex justify-between items-center mb-2">
            <h3 class="font-bold text-sm">
              {{ notification.sender.name }}
            </h3>
            <button
              @click="removeNotification(notification.id)"
              class="text-gray-500 hover:text-gray-700"
            >
              ✕
            </button>
          </div>

          <p class="text-sm text-gray-600 mb-2">
            {{ notification.message }}
          </p>

          <div class="flex justify-between items-center">
            <span class="text-xs text-gray-400">
              {{ formatTime(notification.timestamp) }}
            </span>

            <button
              @click="handleNotificationClick(notification)"
              class="text-sm text-blue-600 hover:text-blue-800 font-semibold"
            >
              View
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="max-w-4xl mx-auto p-6 space-y-6">
      <!-- Header with Group Creation Button -->
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-gray-800">
          Conversations
        </h2>
        <button
          @click="showGroupModal = true"
          class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition"
        >
          Create Group
        </button>
      </div>

      <!-- Search Users -->
      <div class="mb-4 relative">
        <input
          v-model="searchUser"
          type="text"
          placeholder="Search users to start a new conversation..."
          class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400 focus:outline-none"
        />
        <!-- Clear search button -->
        <button
          v-if="searchUser"
          @click="searchUser = ''"
          class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600"
        >
          ✕
        </button>
      </div>

      <!-- Error Handling -->
      <div v-if="error" class="text-red-500 mb-4">
        {{ error }}
      </div>

      <!-- Existing Conversations -->
      <div class="bg-white p-4 rounded-lg shadow-md">
        <h3 class="text-lg font-semibold mb-3">Your Conversations</h3>

        <!-- Loading Indicator for Conversations -->
        <div v-if="loading.conversations" class="text-center text-gray-500">
          Loading conversations...
        </div>

        <!-- Conversations List -->
        <ul v-else-if="conversations.length" class="divide-y divide-gray-200">
          <li
            v-for="conversation in conversations"
            :key="conversation.id"
            class="flex items-center p-3 hover:bg-gray-100 rounded-md transition cursor-pointer"
            @click="goToConversation(conversation.id)"
          >
            <!-- User Profile Picture -->
            <img
              :src="conversation.other_user.profile_picture"
              :alt="`${conversation.other_user.name}'s avatar`"
              class="w-10 h-10 rounded-full object-cover mr-3"
            />

            <div class="flex-1">
              <span class="text-gray-800 font-medium">
                <div v-if="conversation.type ==='group'">{{ conversation.name }}</div>
                <div v-else>{{ conversation.other_user.name }}</div>
              </span>
              <p v-if="conversation.last_message" class="text-xs text-gray-500">
                {{ conversation.last_message.content }}
              </p>
              <p v-else class="text-xs text-gray-500">
                No messages yet
              </p>
            </div>
          </li>
        </ul>

        <p v-else class="text-gray-500 text-center">
          No conversations yet. Start a new chat!
        </p>
      </div>

      <!-- New Conversation Users -->
      <div class="bg-white p-4 rounded-lg shadow-md">
        <h3 class="text-lg font-semibold mb-3">Start a New Conversation</h3>

        <!-- Loading Indicator for Users -->
        <div v-if="loading.users" class="text-center text-gray-500">
          Searching for users...
        </div>

        <!-- Users List -->
        <ul v-else-if="users.length" class="divide-y divide-gray-200">
          <li
            v-for="user in users"
            :key="user.id"
            class="flex items-center p-3 hover:bg-gray-100 rounded-md transition cursor-pointer"
            @click="startConversation(user)"
          >
            <!-- User Profile Picture with fallback -->
            <img
              :src="user.profile_picture || '/default-avatar.png'"
              :alt="`${user.name}'s avatar`"
              class="w-10 h-10 rounded-full object-cover mr-3"
            />

            <div class="flex-1">
              <span class="text-gray-800 font-medium">{{ user.name }}</span>
            </div>
          </li>
        </ul>

        <p v-else-if="!loading.users" class="text-gray-500 text-center">
          {{ searchUser ? 'No users found' : 'Start searching to find users' }}
        </p>
      </div>

      <!-- Group Creation Modal -->
      <div
        v-if="showGroupModal"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      >
        <div class="bg-white p-6 rounded-lg w-full max-w-md">
          <h3 class="text-xl font-semibold mb-4">Create Group</h3>

          <!-- Group Name Input -->
          <input
            v-model="groupName"
            type="text"
            placeholder="Enter group name"
            class="w-full px-3 py-2 border rounded-lg mb-4"
          />

          <!-- User Search for Group -->
          <div class="mb-4 relative">
            <input
              v-model="searchUser"
              type="text"
              placeholder="Search users to add to group..."
              class="w-full px-4 py-2 border rounded-lg"
            />
          </div>

          <!-- Selected Users -->
          <div v-if="selectedGroupUsers.length" class="mb-4">
            <h4 class="font-medium mb-2">Selected Users:</h4>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="user in selectedGroupUsers"
                :key="user.id"
                class="bg-blue-100 px-2 py-1 rounded-full text-sm flex items-center"
              >
                {{ user.name }}
                <button
                  @click="toggleUserSelection(user)"
                  class="ml-2 text-red-500"
                >
                  ✕
                </button>
              </span>
            </div>
          </div>

          <!-- Users List -->
          <ul v-if="users.length" class="max-h-60 overflow-y-auto divide-y divide-gray-200">
            <li
              v-for="user in users"
              :key="user.id"
              class="flex items-center p-3 hover:bg-gray-100 rounded-md transition cursor-pointer"
              :class="{
                'bg-blue-50': selectedGroupUsers.some(u => u.id === user.id)
              }"
              @click="toggleUserSelection(user)"
            >
              <img
                :src="user.profile_picture || '/default-avatar.png'"
                :alt="`${user.name}'s avatar`"
                class="w-10 h-10 rounded-full object-cover mr-3"
              />
              <span class="text-gray-800 font-medium">{{ user.name }}</span>
            </li>
          </ul>

          <!-- Modal Actions -->
          <div class="flex justify-end space-x-2 mt-4">
            <button
              @click="showGroupModal = false"
              class="px-4 py-2 bg-gray-200 rounded-lg"
            >
              Cancel
            </button>
            <button
              @click="createGroup"
              :disabled="loading.groups"
              class="px-4 py-2 bg-blue-500 text-white rounded-lg
                     hover:bg-blue-600 transition disabled:opacity-50"
            >
              {{ loading.groups ? 'Creating...' : 'Create Group' }}
            </button>
          </div>

          <!-- Error Message -->
          <div v-if="error" class="text-red-500 mt-2">
            {{ error }}
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.transition {
  transition: background-color 0.2s ease;
}

@keyframes slide-in {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.animate-slide-in {
  animation: slide-in 0.3s ease-out;
}
</style>