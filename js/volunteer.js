// js/volunteer.js — Main application logic with database integration

(function () {
  'use strict';

  // State management
  const state = {
      currentBeneficiaryId: null,
      beneficiaries: [],
      centers: [],
      inventory: [],
      specialNeeds: [],
      currentPage: 1,
      totalPages: 1,
      loading: false
  };

  // DOM Helpers
  function byId(id) {
      return document.getElementById(id);
  }

  function setText(id, value) {
      const el = byId(id);
      if (el) el.textContent = value;
  }

  function setHtml(id, html) {
      const el = byId(id);
      if (el) el.innerHTML = html;
  }

  function setValue(id, value) {
      const el = byId(id);
      if (el) el.value = value;
  }

  function showElement(id) {
      const el = byId(id);
      if (el) el.classList.remove('hidden');
  }

  function hideElement(id) {
      const el = byId(id);
      if (el) el.classList.add('hidden');
  }

  function openModal(id) {
      const modal = byId(id);
      if (modal) modal.classList.add('open');
  }

  function closeModal(id) {
      const modal = byId(id);
      if (modal) modal.classList.remove('open');
  }

  // Format helpers
  function formatNumber(value) {
      return Number(value || 0).toLocaleString();
  }

  function formatDate(dateStr) {
      if (!dateStr) return '-';
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  // =====================
  // LOGIN FUNCTIONALITY
  // =====================
  function setupLoginForm() {
      const form = byId('loginForm');
      if (!form) return;

      form.addEventListener('submit', async function (e) {
          e.preventDefault();

          const btn = form.querySelector('button[type="submit"]');
          const messageEl = byId('loginMessage');
          const usernameInput = byId('username');
          const passwordInput = byId('password');

          if (!btn || !usernameInput || !passwordInput) return;

          const username = usernameInput.value.trim();
          const password = passwordInput.value;

          if (!username || !password) {
              if (messageEl) {
                  messageEl.textContent = 'Please enter your username and password.';
                  messageEl.style.color = 'var(--orange)';
              }
              return;
          }

          btn.textContent = 'Signing in...';
          btn.disabled = true;
          if (messageEl) messageEl.textContent = '';

          try {
              const data = await window.API.login(username, password);
              window.location.href = data.user?.redirect || 'dashboard.html';
          } catch (error) {
              if (messageEl) {
                  messageEl.textContent = error.message || 'Unable to sign in.';
                  messageEl.style.color = 'var(--orange)';
              }
              btn.textContent = 'Sign In ->';
              btn.disabled = false;
          }
      });
  }

  // =====================
  // AUTH GUARD
  // =====================
  async function setupAuthGuard() {
      const protectedPages = ['dashboard.html', 'records.html', 'inventory.html', 'evac-centers.html', 'reports.html', 'profile.html', 'backup-restore.html'];
      const currentPage = window.location.pathname.split('/').pop() || 'index.html';

      if (!protectedPages.includes(currentPage)) return;

      try {
          const data = await window.API.getCurrentUser();
          if (!data.success) {
              window.location.href = 'login.html';
          } else {
              updateUserDisplay(data.user);
          }
      } catch {
          window.location.href = 'login.html';
      }
  }

  function updateUserDisplay(user) {
      const avatarEls = document.querySelectorAll('.user-avatar');
      const nameEls = document.querySelectorAll('.user-name, .sidebar-user-name');
      const roleEls = document.querySelectorAll('.user-role, .sidebar-user-role');

      const initials = user.name ? user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) : 'U';

      avatarEls.forEach(el => el.textContent = initials);
      nameEls.forEach(el => el.textContent = user.name || user.username);
      roleEls.forEach(el => el.textContent = user.role || 'User');
  }

  // =====================
  // DASHBOARD
  // =====================
  async function loadDashboard() {
      if (!byId('kpiBeneficiaries')) return;

      try {
          // Load KPIs
          const kpiData = await window.API.getDashboardKPIs();
          if (kpiData.success && kpiData.data) {
              setText('kpiBeneficiaries', formatNumber(kpiData.data.total_beneficiaries));
              setText('kpiActiveCenters', formatNumber(kpiData.data.active_centers));
              setText('kpiDistributions', formatNumber(kpiData.data.total_distributions));
              setText('kpiDistributedToday', formatNumber(kpiData.data.distributed_today));
          }

          // Load inventory status
          const invData = await window.API.getInventory();
          if (invData.success && invData.data) {
              renderInventoryStatus(invData.data);
          }

          // Load activity feed
          const actData = await window.API.getActivityFeed();
          if (actData.success && actData.data) {
              renderActivityFeed(actData.data);
          }

          // Load recent distributions
          const distData = await window.API.getDistributions({ limit: 5 });
          if (distData.success && distData.data) {
              renderRecentDistributions(distData.data);
          }

          // Update welcome message
          const user = await window.API.getCurrentUser();
          if (user.success && user.user) {
              const hour = new Date().getHours();
              const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
              const firstName = user.user.name ? user.user.name.split(' ')[0] : 'User';
              setText('welcomeTitle', `${greeting}, ${firstName} 👋`);
              setText('welcomeSubtitle', `Here's the latest status of CDRC relief operations.`);
          }
      } catch (error) {
          console.error('Dashboard load error:', error);
      }
  }

  function renderInventoryStatus(items) {
      const container = byId('inventoryList');
      if (!container) return;

      if (!items.length) {
          container.innerHTML = '<div class="inv-row"><div class="inv-row-top"><span>No inventory records found.</span></div></div>';
          return;
      }

      const html = items.slice(0, 5).map(item => {
          const percent = Math.min(100, Math.max(0, Math.round((item.current_stock / Math.max(1, item.reorder_level)) * 100)));
          const colorClass = item.current_stock <= item.reorder_level ? 'warn' : 'ok';
          return `
              <div class="inv-row">
                  <div class="inv-row-top">
                      <span>${item.item_name}</span>
                      <span style="font-weight:600;">${formatNumber(item.current_stock)} ${item.unit || ''}</span>
                  </div>
                  <div class="inv-bar"><div class="inv-fill ${colorClass}" style="width:${percent}%;"></div></div>
              </div>`;
      }).join('');

      container.innerHTML = html;
  }

  function renderActivityFeed(feed) {
      const container = byId('activityFeed');
      if (!container) return;

      if (!feed.length) {
          container.innerHTML = '<div class="act-item"><div class="act-dot" style="background:var(--gray-300);"></div><div><div class="act-text">No recent activity yet.</div></div></div>';
          return;
      }

      const html = feed.slice(0, 5).map(item => {
          const dot = item.type === 'distribution' ? 'var(--teal)' : 'var(--orange)';
          return `
              <div class="act-item">
                  <div class="act-dot" style="background:${dot};"></div>
                  <div>
                      <div class="act-text">${item.text}</div>
                      <div class="act-time">${item.detail || ''}</div>
                  </div>
              </div>`;
      }).join('');

      container.innerHTML = html;
  }

  function renderRecentDistributions(rows) {
      const tbody = byId('recentDistributionsBody');
      if (!tbody) return;

      if (!rows.length) {
          tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--gray-500);">No distribution records found.</td></tr>';
          return;
      }

      const html = rows.map(row => `
          <tr>
              <td>${row.beneficiary_name || 'Unknown'}</td>
              <td>${row.center_name || 'Unknown'}</td>
              <td>${row.items_given || '—'}</td>
              <td>${row.dist_date || '—'}</td>
              <td><span class="badge badge-green">Completed</span></td>
          </tr>`).join('');

      tbody.innerHTML = html;
  }

  // =====================
  // BENEFICIARIES PAGE
  // =====================
  async function loadBeneficiaries() {
      const tbody = byId('tableBody');
      if (!tbody) return;

      const searchInput = byId('searchInput');
      const statusFilter = byId('statusFilter');
      const centerFilter = byId('centerFilter');

      const params = {
          page: state.currentPage,
          limit: 10,
          search: searchInput?.value || '',
          status: statusFilter?.value || '',
          center: centerFilter?.value || ''
      };

      try {
          // Load centers for filter dropdown
          if (centerFilter && !state.centers.length) {
              const centerData = await window.API.getCenters();
              if (centerData.success) {
                  state.centers = centerData.data;
                  const options = '<option value="">All Centers</option>' +
                      state.centers.map(c => `<option value="${c.center_name}">${c.center_name}</option>`).join('');
                  centerFilter.innerHTML = options;
              }
          }

          // Load beneficiaries
          const data = await window.API.getBeneficiaries(params);

          if (data.success) {
              state.beneficiaries = data.data;
              state.totalPages = data.total_pages;
              renderBeneficiaryTable(data.data);
              setText('recordCount', `Showing ${data.data.length} of ${formatNumber(data.total)} records`);
              updatePagination(data.page, data.total_pages);
          }

          // Load stats
          const stats = await window.API.getBeneficiaryStats();
          if (stats.success && stats.data) {
              const summaryNum = document.querySelector('.summary-number');
              if (summaryNum) summaryNum.textContent = formatNumber(stats.data.total);
          }
      } catch (error) {
          console.error('Error loading beneficiaries:', error);
          tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--orange);">Failed to load records.</td></tr>';
      }
  }

  function renderBeneficiaryTable(beneficiaries) {
      const tbody = byId('tableBody');
      if (!tbody) return;

      if (!beneficiaries.length) {
          tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--gray-500);">No beneficiaries found.</td></tr>';
          return;
      }

      const statusBadge = (status) => {
          const classes = {
              'Served': 'badge-green',
              'Pending': 'badge-orange',
              'Priority': 'badge-teal'
          };
          return `<span class="badge ${classes[status] || 'badge-gray'}">${status}</span>`;
      };

      const html = beneficiaries.map(b => `
          <tr>
              <td>
                  <div class="beneficiary-name">${b.full_name}</div>
                  <div class="beneficiary-id">${b.beneficiary_code}</div>
              </td>
              <td>${b.household_size} members</td>
              <td>${b.center_name}</td>
              <td>${b.special_need || 'None'}</td>
              <td>${statusBadge(b.status)}</td>
              <td>${b.registered_at || '-'}</td>
              <td>
                  <div class="action-btns">
                      <button class="action-btn view" onclick="viewBeneficiary(${b.beneficiary_id})">View</button>
                      <button class="action-btn edit" onclick="editBeneficiary(${b.beneficiary_id})">Edit</button>
                      <button class="action-btn del" onclick="deleteBeneficiaryConfirm(${b.beneficiary_id})">Delete</button>
                  </div>
              </td>
          </tr>
      `).join('');

      tbody.innerHTML = html;
  }

  function updatePagination(currentPage, totalPages) {
      const container = document.querySelector('.pagination');
      if (!container) return;

      const info = container.querySelector('.pagination-info');
      const btns = container.querySelector('.pagination-btns');

      if (info) info.textContent = `Page ${currentPage} of ${totalPages}`;

      if (btns) {
          let html = `<button class="pg-btn" onclick="changePage(${currentPage - 1})" ${currentPage <= 1 ? 'disabled' : ''}>Previous</button>`;

          for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
              html += `<button class="pg-btn ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
          }

          html += `<button class="pg-btn" onclick="changePage(${currentPage + 1})" ${currentPage >= totalPages ? 'disabled' : ''}>Next</button>`;
          btns.innerHTML = html;
      }
  }

  window.changePage = function (page) {
      if (page < 1 || page > state.totalPages) return;
      state.currentPage = page;
      loadBeneficiaries();
  };

  window.viewBeneficiary = async function (id) {
      try {
          const data = await window.API.getBeneficiary(id);
          if (data.success && data.data) {
              const b = data.data;
              state.currentBeneficiaryId = id;

              setText('viewModalTitle', `${b.first_name} ${b.last_name}`);
              setText('detailId', b.beneficiary_code);
              setText('detailName', `${b.first_name} ${b.last_name}`);
              setText('detailHousehold', b.household_size);
              setText('detailCenter', b.center_name);
              setText('detailNeeds', b.need_label || 'None');
              setText('detailStatus', b.status);
              setText('detailAddress', `${b.address}, ${b.barangay}, ${b.city}`);
              setText('detailRegistered', formatDate(b.registered_at));

              // Load distribution history
              const distData = await window.API.getDistributions({ beneficiary_id: id });
              renderDistributionHistory(distData.data || []);

              hideElement('editSection');
              hideElement('editSectionTitle');
              hideElement('saveChangesBtn');
              hideElement('distributionForm');
              hideElement('distributionFormTitle');
              hideElement('saveDistributionBtn');

              openModal('viewModal');
          }
      } catch (error) {
          alert('Failed to load beneficiary details.');
      }
  };

  function renderDistributionHistory(distributions) {
      const tbody = byId('distributionHistoryBody');
      if (!tbody) return;

      if (!distributions || !distributions.length) {
          tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:var(--gray-500);">No distribution records yet.</td></tr>';
          return;
      }

      const html = distributions.map(d => `
          <tr>
              <td>${d.dist_date || formatDate(d.distribution_date)}</td>
              <td>${d.items_given || '—'}</td>
              <td>${d.household_size || '—'}</td>
              <td><span class="badge badge-green">Completed</span></td>
          </tr>
      `).join('');

      tbody.innerHTML = html;
  }

  window.editBeneficiary = async function (id) {
      await viewBeneficiary(id);

      const data = await window.API.getBeneficiary(id);
      if (data.success && data.data) {
          const b = data.data;

          setValue('editName', `${b.first_name} ${b.last_name}`);
          setValue('editHousehold', b.household_size);
          setValue('editAddress', b.address);

          // Load centers for dropdown
          await loadCentersDropdown('editCenter', b.center_id);
          await loadSpecialNeedsDropdown('editNeeds', b.need_id);

          showElement('editSection');
          showElement('editSectionTitle');
          showElement('saveChangesBtn');
      }
  };

  async function loadCentersDropdown(selectId, selectedId = null) {
      const select = byId(selectId);
      if (!select) return;

      if (!state.centers.length) {
          const data = await window.API.getCenters();
          if (data.success) state.centers = data.data;
      }

      const html = state.centers.map(c =>
          `<option value="${c.center_id}" ${c.center_id == selectedId ? 'selected' : ''}>${c.center_name}</option>`
      ).join('');

      select.innerHTML = html;
  }

  async function loadSpecialNeedsDropdown(selectId, selectedId = null) {
      const select = byId(selectId);
      if (!select) return;

      try {
          const data = await window.API.getSpecialNeeds();
          if (data.success && data.data) {
              state.specialNeeds = data.data;
              const html = data.data.map(n =>
                  `<option value="${n.need_id}" ${n.need_id == selectedId ? 'selected' : ''}>${n.need_label}</option>`
              ).join('');
              select.innerHTML = html;
          }
      } catch {
          // Use defaults if API not available
          select.innerHTML = `
              <option value="1">None</option>
              <option value="2">Elderly (60+)</option>
              <option value="3">Infant / Young Child</option>
              <option value="4">Medical Condition</option>
              <option value="5">Person with Disability</option>
              <option value="6">Pregnant / Lactating</option>
          `;
      }
  }

  window.deleteBeneficiaryConfirm = async function (id) {
      if (!confirm('Are you sure you want to delete this beneficiary?')) return;

      try {
          await window.API.deleteBeneficiary(id);
          alert('Beneficiary deleted successfully.');
          loadBeneficiaries();
      } catch (error) {
          alert(error.message || 'Failed to delete beneficiary.');
      }
  };

  window.openAddModal = async function () {
      // Reset form
      byId('fullName')?.value && (byId('fullName').value = '');
      byId('householdCount')?.value && (byId('householdCount').value = '');
      byId('address')?.value && (byId('address').value = '');

      await loadCentersDropdown('evacCenter');
      await loadSpecialNeedsDropdown('specialNeeds');

      openModal('addModal');
  };

  window.closeAddModal = function () {
      closeModal('addModal');
  };

  window.closeViewModal = function () {
      closeModal('viewModal');
  };

  window.saveRecord = async function () {
      const fullName = byId('fullName')?.value.trim();
      const household = byId('householdCount')?.value;
      const centerId = byId('evacCenter')?.value;
      const needId = byId('specialNeeds')?.value;
      const address = byId('address')?.value.trim();

      if (!fullName || !household || !address) {
          alert('Please fill in all required fields.');
          return;
      }

      const nameParts = fullName.split(' ');
      const firstName = nameParts[0];
      const lastName = nameParts.slice(1).join(' ') || 'Unknown';

      try {
          await window.API.createBeneficiary({
              first_name: firstName,
              last_name: lastName,
              household_size: parseInt(household),
              center_id: parseInt(centerId),
              need_id: parseInt(needId) || 1,
              address: address,
              barangay: 'Unknown',
              city: 'Quezon City',
              status: 'Pending'
          });

          alert('Beneficiary registered successfully.');
          closeAddModal();
          loadBeneficiaries();
      } catch (error) {
          alert(error.message || 'Failed to save beneficiary.');
      }
  };

  window.saveChanges = async function () {
      if (!state.currentBeneficiaryId) return;

      const fullName = byId('editName')?.value.trim();
      const household = byId('editHousehold')?.value;
      const centerId = byId('editCenter')?.value;
      const needId = byId('editNeeds')?.value;
      const address = byId('editAddress')?.value.trim();

      const nameParts = fullName.split(' ');
      const firstName = nameParts[0];
      const lastName = nameParts.slice(1).join(' ') || 'Unknown';

      try {
          await window.API.updateBeneficiary(state.currentBeneficiaryId, {
              first_name: firstName,
              last_name: lastName,
              household_size: parseInt(household),
              center_id: parseInt(centerId),
              need_id: parseInt(needId) || 1,
              address: address,
              barangay: 'Unknown',
              city: 'Quezon City'
          });

          alert('Beneficiary updated successfully.');
          closeViewModal();
          loadBeneficiaries();
      } catch (error) {
          alert(error.message || 'Failed to update beneficiary.');
      }
  };

  window.toggleEditSection = function () {
      const section = byId('editSection');
      const title = byId('editSectionTitle');
      const button = byId('saveChangesBtn');

      if (section?.classList.contains('hidden')) {
          showElement('editSection');
          showElement('editSectionTitle');
          showElement('saveChangesBtn');
      } else {
          hideElement('editSection');
          hideElement('editSectionTitle');
          hideElement('saveChangesBtn');
      }
  };

  window.toggleDistributionForm = function () {
      const section = byId('distributionForm');
      if (section?.classList.contains('hidden')) {
          showElement('distributionForm');
          showElement('distributionFormTitle');
          showElement('saveDistributionBtn');
      } else {
          hideElement('distributionForm');
          hideElement('distributionFormTitle');
          hideElement('saveDistributionBtn');
      }
  };

  window.filterTable = function () {
      state.currentPage = 1;
      loadBeneficiaries();
  };

  // =====================
  // INVENTORY PAGE
  // =====================
  async function loadInventory() {
      const tbody = byId('inventoryTableBody');
      if (!tbody) return;

      const searchInput = byId('searchInput');
      const activeChip = document.querySelector('.filter-chip.active');

      const params = {
          search: searchInput?.value || '',
          category_id: ''
      };

      if (activeChip && activeChip.dataset.category !== 'All') {
          // Map category name to ID - you might need to adjust this
          const categoryMap = { 'Food': 1, 'Water': 2, 'Hygiene': 3, 'Medicine': 4, 'Shelter': 5 };
          params.category_id = categoryMap[activeChip.dataset.category] || '';
      }

      try {
          const data = await window.API.getInventory(params);

          if (data.success) {
              state.inventory = data.data;
              renderInventoryTable(data.data);
              setText('itemCount', `${data.data.length} item${data.data.length !== 1 ? 's' : ''}`);
          }
      } catch (error) {
          console.error('Error loading inventory:', error);
      }
  }

  function renderInventoryTable(items) {
      const tbody = byId('inventoryTableBody');
      if (!tbody) return;

      if (!items.length) {
          tbody.innerHTML = '<tr><td colspan="4" style="padding:18px 12px;color:var(--gray-500);">No inventory items found.</td></tr>';
          return;
      }

      const html = items.map(item => {
          const statusBadge = item.stock_status === 'Low Stock'
              ? '<span class="badge badge-orange">Low Stock</span>'
              : '<span class="badge badge-green">OK</span>';

          return `
              <tr class="inventory-row" onclick="selectInventoryItem(${item.item_id})">
                  <td>
                      <div class="item-name">${item.item_name}</div>
                      <div class="item-sub">Reorder at: ${item.reorder_level}</div>
                  </td>
                  <td>${item.category_name}</td>
                  <td><span class="stock-num">${item.current_stock}</span> ${item.unit}</td>
                  <td>${statusBadge}</td>
              </tr>
          `;
      }).join('');

      tbody.innerHTML = html;
  }

  window.selectInventoryItem = async function (id) {
      const item = state.inventory.find(i => i.item_id === id);
      if (!item) return;

      const panel = byId('detailsPanel');
      const badge = byId('selectedCategoryBadge');

      if (badge) badge.textContent = item.category_name;

      const statusBadge = item.stock_status === 'Low Stock'
          ? '<span class="badge badge-orange">Low Stock</span>'
          : '<span class="badge badge-green">OK</span>';

      panel.innerHTML = `
          <div class="detail-head">
              <h3>${item.item_name}</h3>
              <div class="detail-meta">
                  <span class="badge badge-gray">${item.category_name}</span>
                  ${statusBadge}
              </div>
          </div>

          <div class="stock-summary">
              <div class="mini-stat">
                  <div class="mini-stat-label">Current Stock</div>
                  <div class="mini-stat-value">${item.current_stock}</div>
              </div>
              <div class="mini-stat">
                  <div class="mini-stat-label">Unit</div>
                  <div class="mini-stat-value">${item.unit}</div>
              </div>
              <div class="mini-stat">
                  <div class="mini-stat-label">Reorder Level</div>
                  <div class="mini-stat-value">${item.reorder_level}</div>
              </div>
          </div>

          <div class="form-block">
              <h5>Add Stocks</h5>
              <div class="form-row">
                  <div class="field">
                      <label for="addStockQty">Quantity</label>
                      <input type="number" id="addStockQty" min="1" placeholder="Enter quantity">
                  </div>
                  <div class="field">
                      <label for="addStockSource">Source</label>
                      <input type="text" id="addStockSource" placeholder="e.g. Warehouse delivery">
                  </div>
                  <button class="btn btn-primary" onclick="addStock(${item.item_id})">Add Stock</button>
              </div>
          </div>
      `;

      // Highlight selected row
      document.querySelectorAll('.inventory-row').forEach(row => row.classList.remove('active'));
      event.currentTarget.classList.add('active');
  };

  window.addStock = async function (itemId) {
      const qty = parseInt(byId('addStockQty')?.value);
      const source = byId('addStockSource')?.value.trim();

      if (!qty || qty < 1) {
          alert('Please enter a valid quantity.');
          return;
      }

      if (!source) {
          alert('Please enter the stock source.');
          return;
      }

      try {
          await window.API.stockIn(itemId, qty, source);
          alert('Stock added successfully.');
          loadInventory();
      } catch (error) {
          alert(error.message || 'Failed to add stock.');
      }
  };

  // =====================
  // EVACUATION CENTERS
  // =====================
  async function loadCenters() {
      const grid = byId('centersGrid');
      if (!grid) return;

      try {
          const data = await window.API.getCenters();

          if (data.success) {
              state.centers = data.data;
              renderCentersGrid(data.data);
              updateCentersSummary(data.data);
          }
      } catch (error) {
          console.error('Error loading centers:', error);
      }
  }

  function renderCentersGrid(centers) {
      const grid = byId('centersGrid');
      const emptyState = byId('emptyState');

      if (!centers.length) {
          grid.innerHTML = '';
          if (emptyState) emptyState.style.display = 'block';
          return;
      }

      if (emptyState) emptyState.style.display = 'none';

      const html = centers.map((center, index) => `
          <div class="center-card">
              <div class="center-card-header">
                  <div>
                      <div class="center-name">${center.center_name}</div>
                      <div class="center-sub">${center.barangay}, ${center.city}</div>
                  </div>
                  <span class="badge ${center.status === 'Active' ? 'badge-green' : 'badge-orange'}">${center.status}</span>
              </div>

              <div class="center-card-body">
                  <div class="info-row">
                      <div class="info-label">Contact Person</div>
                      <div class="info-value">${center.contact_person || 'Not specified'}</div>
                  </div>

                  <div class="info-row">
                      <div class="info-label">Contact Number</div>
                      <div class="info-value">${center.contact_no || 'Not specified'}</div>
                  </div>

                  <div class="center-stats">
                      <div class="stat-box">
                          <div class="stat-box-num">${center.total_beneficiaries || 0}</div>
                          <div class="stat-box-label">Beneficiaries</div>
                      </div>
                      <div class="stat-box">
                          <div class="stat-box-num">${center.current_occupancy}/${center.max_capacity}</div>
                          <div class="stat-box-label">Capacity</div>
                      </div>
                  </div>

                  <div class="card-actions">
                      <button class="btn btn-outline" style="padding:8px 12px;font-size:12px;" onclick="viewCenterBeneficiaries('${center.center_name}')">
                          View Beneficiaries
                      </button>
                      <button class="btn btn-outline" style="padding:8px 12px;font-size:12px;color:var(--orange);border-color:rgba(232,75,26,0.25);" onclick="deleteCenterConfirm(${center.center_id})">
                          Remove
                      </button>
                  </div>
              </div>
          </div>
      `).join('');

      grid.innerHTML = html;
  }

  function updateCentersSummary(centers) {
      const total = centers.length;
      const active = centers.filter(c => c.status === 'Active').length;

      setText('totalCenters', total);
      setText('activeCenters', active);
  }

  window.viewCenterBeneficiaries = function (centerName) {
      window.location.href = `records.html?center=${encodeURIComponent(centerName)}`;
  };

  window.deleteCenterConfirm = async function (id) {
      if (!confirm('Are you sure you want to remove this center?')) return;

      try {
          await window.API.deleteCenter(id);
          alert('Center removed successfully.');
          loadCenters();
      } catch (error) {
          alert(error.message || 'Failed to remove center.');
      }
  };

  window.openCenterModal = function () {
      openModal('centerModal');
  };

  window.closeCenterModal = function () {
      closeModal('centerModal');
      // Reset form
      ['centerName', 'centerStatus', 'centerBeneficiaries', 'centerContactPerson', 'centerContactNumber', 'centerEmail', 'centerAddress'].forEach(id => {
          const el = byId(id);
          if (el) el.value = el.tagName === 'SELECT' ? 'Active' : '';
      });
  };

  window.addCenter = async function () {
      const name = byId('centerName')?.value.trim();
      const status = byId('centerStatus')?.value;
      const capacity = byId('centerBeneficiaries')?.value;
      const contactPerson = byId('centerContactPerson')?.value.trim();
      const contactNo = byId('centerContactNumber')?.value.trim();
      const address = byId('centerAddress')?.value.trim();

      if (!name || !contactPerson || !address) {
          alert('Please fill in all required fields.');
          return;
      }

      try {
          await window.API.createCenter({
              center_name: name,
              barangay: address.split(',')[0] || address,
              city: 'Quezon City',
              province: 'Metro Manila',
              max_capacity: parseInt(capacity) || 100,
              current_occupancy: 0,
              status: status,
              contact_person: contactPerson,
              contact_no: contactNo
          });

          alert('Center added successfully.');
          closeCenterModal();
          loadCenters();
      } catch (error) {
          alert(error.message || 'Failed to add center.');
      }
  };

  // =====================
  // LOGOUT
  // =====================
  window.logout = async function () {
      try {
          await window.API.logout();
      } catch {
          // Continue even if API fails
      }
      window.location.href = 'login.html';
  };

  // =====================
  // INITIALIZATION
  // =====================
  function init() {
      setupLoginForm();
      setupAuthGuard();

      // Page-specific initialization
      const currentPage = window.location.pathname.split('/').pop();

      switch (currentPage) {
          case 'dashboard.html':
              loadDashboard();
              break;
          case 'records.html':
              loadBeneficiaries();
              break;
          case 'inventory.html':
              loadInventory();
              // Setup filter chips
              document.querySelectorAll('.filter-chip').forEach(chip => {
                  chip.addEventListener('click', function () {
                      document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
                      this.classList.add('active');
                      loadInventory();
                  });
              });
              // Setup search
              byId('searchInput')?.addEventListener('input', loadInventory);
              break;
          case 'evac-centers.html':
              loadCenters();
              break;
      }

      // Modal backdrop close
      document.querySelectorAll('.modal-overlay').forEach(modal => {
          modal.addEventListener('click', function (e) {
              if (e.target === this) {
                  this.classList.remove('open');
              }
          });
      });
  }

  document.addEventListener('DOMContentLoaded', init);
})();
