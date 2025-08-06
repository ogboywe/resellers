        <!-- JavaScript -->
            // Tab Navigation
            function openTab(tabName) {
                // Hide all tab content
                const tabContents = document.querySelectorAll('.tab-content');
                tabContents.forEach(content => {
                    content.classList.remove('active');
                });
                
                // Show selected tab content
                document.getElementById(tabName + '-content').classList.add('active');
                
                // Update active tab in sidebar
                const tabLinks = document.querySelectorAll('.sidebar-menu a');
                tabLinks.forEach(link => {
                    link.classList.remove('active');
                });
                
                // Find the link with href="#tabName" and add active class
                const activeLink = document.querySelector(`.sidebar-menu a[href="#${tabName}"]`);
                if (activeLink) {
                    activeLink.classList.add('active');
                }
                
                // Close sidebar on mobile after tab selection
                if (window.innerWidth < 992) {
                    document.getElementById('sidebar').classList.remove('show');
                }
                
                // Update URL hash
                window.location.hash = tabName;
            }
            
            // Report Tab Navigation
            function openReportTab(tabName) {
                // Hide all report tab content
                const tabContents = document.querySelectorAll('#reports-content .tab-content');
                tabContents.forEach(content => {
                    content.classList.remove('active');
                });
                
                // Show selected report tab content
                document.getElementById(tabName + '-content').classList.add('active');
                
                // Update active tab
                const tabLinks = document.querySelectorAll('#reports-content .tab');
                tabLinks.forEach(link => {
                    link.classList.remove('active');
                });
                
                // Find the clicked tab and add active class
                event.currentTarget.classList.add('active');
            }
            
            // Modal Functions
            function openModal(modalId) {
                document.getElementById(modalId).classList.add('show');
            }
            
            function closeModal(modalId) {
                document.getElementById(modalId).classList.remove('show');
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (event.target === modal) {
                        modal.classList.remove('show');
                    }
                });
            });
            
            // Customer Actions
            function viewCustomer(id) {
                // In a real application, this would fetch customer details via AJAX
                // For this example, we'll use the existing data
                const customers = <?php echo json_encode($customers); ?>;
                let customer = null;
                
                for (let i = 0; i < customers.length; i++) {
                    if (customers[i].id == id) {
                        customer = customers[i];
                        break;
                    }
                }
                
                if (customer) {
                    const content = `
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" value="${customer.first_name}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" value="${customer.last_name}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Username</label>
                                    <input type="text" value="${customer.username}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Password</label>
                                    <input type="text" value="${customer.password}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" value="${customer.email}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="tel" value="${customer.phone}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Created</label>
                                    <input type="text" value="${customer.created_at}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Expires</label>
                                    <input type="text" value="${customer.expires_at}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Status</label>
                                    <input type="text" value="${customer.status}" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Created By</label>
                                    <input type="text" value="${customer.created_by}" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea readonly rows="3">${customer.notes || ''}</textarea>
                        </div>
                    `;
                    
                    document.getElementById('view-customer-content').innerHTML = content;
                    openModal('view-customer-modal');
                }
            }
            
            function renewCustomer(id) {
                // In a real application, this would fetch customer details via AJAX
                // For this example, we'll use the existing data
                const customers = <?php echo json_encode($customers); ?>;
                let customer = null;
                
                for (let i = 0; i < customers.length; i++) {
                    if (customers[i].id == id) {
                        customer = customers[i];
                        break;
                    }
                }
                
                if (customer) {
                    const content = `
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="customer_id" value="${customer.id}">

                            <p>Are you sure you want to renew the account for <strong>${customer.first_name} ${customer.last_name}</strong>?</p>
                            <p>This will extend the expiration date by 1 month and set the status to active.</p>
                            
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeModal('renew-customer-modal')">Cancel</button>
                                <button type="submit" name="renew_customer" class="btn btn-success">Renew</button>
                            </div>
                        </form>
                    `;
                    
                    document.getElementById('renew-customer-content').innerHTML = content;
                    openModal('renew-customer-modal');
                }
            }
            
            // Reseller Actions (Owner Only)
            <?php if ($_SESSION['role'] === 'owner'): ?>
                function editReseller(username) {
                    // In a real application, this would fetch reseller details via AJAX
                    // For this example, we'll use the existing data
                    const users = <?php echo json_encode($users); ?>;
                    const reseller = users[username];
                    
                    if (reseller) {
                        const content = `
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="reseller_name" value="${username}">
                                
                                <div class="form-group">
                                    <label>Username</label>
                                    <input type="text" value="${username}" readonly>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_full_name">Full Name</label>
                                    <input type="text" id="edit_full_name" name="full_name" value="${reseller.full_name}" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_email">Email</label>
                                    <input type="email" id="edit_email" name="email" value="${reseller.email}" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_phone">Phone</label>
                                    <input type="tel" id="edit_phone" name="phone" value="${reseller.phone}" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_status">Status</label>
                                    <select id="edit_status" name="status" required>
                                        <option value="active" ${reseller.status === 'active' ? 'selected' : ''}>Active</option>
                                        <option value="inactive" ${reseller.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                    </select>
                                </div>
                                
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" onclick="closeModal('edit-reseller-modal')">Cancel</button>
                                    <button type="submit" name="update_reseller" class="btn btn-primary">Update</button>
                                </div>
                            </form>
                        `;
                        
                        document.getElementById('edit-reseller-content').innerHTML = content;
                        openModal('edit-reseller-modal');
                    }
                }
                
                function resetResellerPassword(username) {
                    // In a real application, this would fetch reseller details via AJAX
                    // For this example, we'll use the existing data
                    const users = <?php echo json_encode($users); ?>;
                    const reseller = users[username];
                    
                    if (reseller) {
                        const content = `
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="reseller_name" value="${username}">
                                
                                <p>Reset password for reseller <strong>${reseller.full_name}</strong> (${username})</p>
                                
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" required>
                                    <small class="text-muted">Must be at least 8 characters with uppercase, lowercase, and numbers.</small>
                                </div>
                                
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" onclick="closeModal('reset-reseller-password-modal')">Cancel</button>
                                    <button type="submit" name="reset_reseller_password" class="btn btn-warning">Reset Password</button>
                                </div>
                            </form>
                        `;
                        
                        document.getElementById('reset-reseller-password-content').innerHTML = content;
                        openModal('reset-reseller-password-modal');
                    }
                }
                
                function deleteReseller(username) {
                    // In a real application, this would fetch reseller details via AJAX
                    // For this example, we'll use the existing data
                    const users = <?php echo json_encode($users); ?>;
                    const reseller = users[username];
                    
                    if (reseller) {
                        const content = `
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="reseller_name" value="${username}">
                                
                                <p>Are you sure you want to delete the reseller <strong>${reseller.full_name}</strong> (${username})?</p>
                                <p>All customers created by this reseller will be reassigned to you.</p>
                                <p class="text-danger">This action cannot be undone!</p>
                                
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" onclick="closeModal('delete-reseller-modal')">Cancel</button>
                                    <button type="submit" name="delete_reseller" class="btn btn-danger">Delete</button>
                                </div>
                            </form>
                        `;
                        
                        document.getElementById('delete-reseller-content').innerHTML = content;
                        openModal('delete-reseller-modal');
                    }
                }
            <?php endif; ?>
            
            // Notifications Toggle
            const notificationsToggle = document.getElementById('notifications-toggle');
            const notificationsMenu = document.getElementById('notifications-menu');
            
            if (notificationsToggle && notificationsMenu) {
                notificationsToggle.addEventListener('click', function(event) {
                    event.stopPropagation();
                    notificationsMenu.classList.toggle('show');
                });
                
                document.addEventListener('click', function(event) {
                    if (!notificationsMenu.contains(event.target) && event.target !== notificationsToggle) {
                        notificationsMenu.classList.remove('show');
                    }
                });
            }
            
            // Sidebar Toggle for Mobile
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.getElementById('sidebar');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
                
                document.addEventListener('click', function(event) {
                    if (window.innerWidth < 992 && !sidebar.contains(event.target) && event.target !== sidebarToggle) {
                        sidebar.classList.remove('show');
                    }
                });
            }
            
            // Search and Filter
            const customerSearch = document.getElementById('customer-search');
            const statusFilter = document.getElementById('status-filter');
            const applyFilters = document.getElementById('apply-filters');
            
            if (applyFilters) {
                applyFilters.addEventListener('click', function() {
                    const searchTerm = customerSearch.value;
                    const status = statusFilter.value;
                    
                    window.location.href = `?search=${encodeURIComponent(searchTerm)}&status=${encodeURIComponent(status)}`;
                });
            }
            
            // Initialize based on URL hash
            document.addEventListener('DOMContentLoaded', function() {
                const hash = window.location.hash.substring(1);
                if (hash) {
                    openTab(hash);
                }
            });
