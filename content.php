
<div class="relative bg-white rounded-lg max-w-2xl w-full p-6 shadow-lg" @click.away="showModal = false">

                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-bold text-gray-800">Staff Member Registration</h2>
                    <button @click="showModal = false" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <?php if ($success_message): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                        <p><?php echo $success_message; ?></p>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <p><?php echo $error_message; ?></p>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                        <input type="text" id="name" name="name"
                            value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input type="email" id="email" name="email"
                            value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input type="password" id="password" name="password" required minlength="8"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Must be at least 8 characters long</p>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm
                            Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                        <select id="role" name="role" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="" disabled selected>Select a role</option>
                            <?php if ($_SESSION['staff_role'] === 'admin'): ?>
                                <option value="admin" <?php echo (isset($role) && $role === 'admin') ? 'selected' : ''; ?>>
                                    Admin</option>
                                <option value="master_agent" <?php echo (isset($role) && $role === 'master_agent') ? 'selected' : ''; ?>>Master Agent</option>
                            <?php endif; ?>
                            <option value="agent" <?php echo (isset($role) && $role === 'agent') ? 'selected' : ''; ?>>
                                Support Agent</option>
                        </select>
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button type="button" @click="showModal = false"
                            class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            Cancel
                        </button>
                        <button type="submit" name="register_staff"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            Register Staff Member
                        </button>
                    </div>
                </form>
            </div>

                            -->
                            <div class="relative bg-white rounded-lg max-w-2xl w-full p-6 shadow-lg" @click.away="showEditModal = false">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800">Edit Staff Member</h2>
                <button @click="showEditModal = false" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <?php if ($success_message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p><?php echo $success_message; ?></p>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <form id="editStaffForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>"
                class="space-y-4">
                <input type="hidden" id="edit_user_id" name="edit_user_id" x-bind:value="editUserId">
                <input type="hidden" name="action" value="update_staff">

                <div>
                    <label for="edit_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" id="edit_name" name="edit_name" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="edit_email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" id="edit_email" name="edit_email" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="edit_password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="edit_password" name="edit_password"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password. New password
                        must be at least 8 characters long.</p>
                </div>

                <div>
                    <label for="edit_role" class="block text-sm font-medium text-gray-700">Role</label>
                    <select id="edit_role" name="edit_role" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <?php if ($_SESSION['staff_role'] === 'admin'): ?>
                            <option value="admin">Admin</option>
                            <option value="master_agent">Master Agent</option>
                        <?php endif; ?>
                        <option value="agent">Support Agent</option>
                    </select>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" @click="showEditModal = false"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Update Staff Member
                    </button>
                </div>
            </form>
        </div>