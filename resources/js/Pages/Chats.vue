<script setup>
import { ref, computed, onMounted } from "vue";
import { useForm, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const showModal = ref(false);
const searchUser = ref("");
const selectedUser = ref(null);
const authUser = usePage().props.auth.user;

const props = defineProps({
  conversations: Array,
  users: Array,
});

// Debugging: Ensure `users` are received
onMounted(() => {
  console.log("Users from Laravel:", props.users);
});

// Filter users dynamically based on search input
const filteredUsers = computed(() => {
  if (!searchUser.value.trim()) return [];
  return props.users.filter(user =>
    user.name.toLowerCase().includes(searchUser.value.toLowerCase())
  );
});

const form = useForm({
  name: "",
  type: "",
  user_id: null,
});

// Select a user and update the form
const selectUser = (user) => {
  selectedUser.value = user;
  searchUser.value = user.name;
  form.user_id = user.id;
};

// Create a new conversation
const createConversation = () => {
  if (!form.name.trim() || !form.type || !form.user_id) return;

  form.post(route('create.chat'), {
    onSuccess: () => {
      form.reset();
      searchUser.value = "";
      selectedUser.value = null;
      showModal.value = false;
    }
  });
};
</script>

<template>
  <AuthenticatedLayout>
    <div class="max-w-4xl mx-auto p-6">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-semibold text-gray-800">Conversations</h2>
        <button
          @click="showModal = true"
          class="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg shadow-md hover:bg-blue-700 transition"
        >
          + New Conversation
        </button>
      </div>

      <!-- Conversations List -->
      <div v-if="conversations.length" class="bg-white p-4 rounded-lg shadow-md">
        <h3 class="text-lg font-semibold mb-3">Your Conversations</h3>
        <ul>
          <li
            v-for="conversation in conversations"
            :key="conversation.id"
            class="flex items-center p-3 border-b last:border-none hover:bg-gray-100 rounded-md transition"
          >
            <!-- User Profile Picture -->
            <img
              :src="conversation.other_user?.profile_picture || '/default-avatar.png'"
              alt="User Avatar"
              class="w-10 h-10 rounded-full object-cover mr-3"
            />

            <div class="flex-1">
              <a :href="`/chat/${conversation.id}`" class="text-blue-600 font-medium block">
                {{ conversation.other_user?.name || "Unknown User" }}
              </a>
              <p class="text-sm text-gray-600 truncate">
                {{ conversation.last_message ? conversation.last_message.body : "No messages yet" }}
              </p>
            </div>
          </li>
        </ul>
      </div>
      <p v-else class="text-gray-500 text-center mt-4">No conversations yet.</p>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md animate-fadeIn">
          <h2 class="text-xl font-semibold mb-4 text-gray-800">Create New Conversation</h2>

          <!-- Conversation Name -->
          <input
            v-model="form.name"
            type="text"
            placeholder="Conversation Name"
            class="w-full px-4 py-2 border rounded-lg mb-3 focus:ring-2 focus:ring-blue-400 focus:outline-none"
          />

          <!-- Select Type -->
          <select
            v-model="form.type"
            class="w-full px-4 py-2 border rounded-lg mb-3 focus:ring-2 focus:ring-blue-400 focus:outline-none"
          >
            <option value="" disabled>Select conversation type</option>
            <option value="private">Private</option>
            <option value="group">Group</option>
          </select>

          <!-- Search & Select User -->
          <div class="relative">
            <input
              v-model="searchUser"
              type="text"
              placeholder="Search users..."
              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-400 focus:outline-none"
            />
            <ul
              v-if="filteredUsers.length"
              class="absolute w-full bg-white border rounded-lg shadow-md max-h-40 overflow-y-auto mt-1"
            >
              <li
                v-for="user in filteredUsers"
                :key="user.id"
                @click="selectUser(user)"
                class="p-2 cursor-pointer hover:bg-blue-100 transition"
              >
                {{ user.name }}
              </li>
            </ul>
          </div>

          <!-- Selected User -->
          <div v-if="selectedUser" class="mt-3 p-2 bg-gray-100 rounded-lg flex justify-between items-center">
            <p class="text-gray-700">{{ selectedUser.name }}</p>
            <button
              @click="selectedUser = null; searchUser = ''; form.user_id = null"
              class="text-red-500 text-sm hover:underline"
            >
              Remove
            </button>
          </div>

          <!-- Actions -->
          <div class="flex justify-end mt-4">
            <button
              @click="showModal = false"
              class="px-4 py-2 bg-gray-300 rounded-lg mr-2 hover:bg-gray-400 transition"
            >
              Cancel
            </button>
            <button
              @click="createConversation"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
            >
              Create
            </button>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style>
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
.animate-fadeIn {
  animation: fadeIn 0.3s ease-in-out;
}
</style>
