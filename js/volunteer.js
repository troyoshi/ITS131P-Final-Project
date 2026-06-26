(function () {
  'use strict';

  const state = {
    currentBeneficiaryId: null,
    beneficiaries: {
      'BEN-0001': {
        id: 'BEN-0001',
        name: 'Santos, Maria L.',
        household: '4',
        center: 'Brgy. Masaya',
        needs: 'Infant',
        status: 'Served',
        address: 'Sikatuna Village, QC',
        registered: 'Jun 10, 2025',
        distributions: [
          { date: '2025-06-10', items: 'Rice, Canned Goods', qty: '2 packs', status: 'Completed' },
          { date: '2025-06-15', items: 'Water, Hygiene Kit', qty: '1 set', status: 'Completed' }
        ]
      },
      'BEN-0002': {
        id: 'BEN-0002',
        name: 'Cruz, Jose P.',
        household: '6',
        center: 'Brgy. Maliwanag',
        needs: 'None',
        status: 'Served',
        address: 'Brgy. Obrero, QC',
        registered: 'Jun 10, 2025',
        distributions: [
          { date: '2025-06-15', items: 'Hygiene Kit', qty: '1 kit', status: 'Completed' }
        ]
      },
      'BEN-0003': {
        id: 'BEN-0003',
        name: 'Reyes, Ana B.',
        household: '3',
        center: 'Brgy. Masaya',
        needs: 'Elderly',
        status: 'Pending',
        address: 'Brgy. Pag-asa, QC',
        registered: 'Jun 11, 2025',
        distributions: [
          { date: '2025-06-14', items: 'Rice, Water', qty: '1 pack', status: 'Processing' }
        ]
      },
      'BEN-0004': {
        id: 'BEN-0004',
        name: 'Lim, Pedro K.',
        household: '5',
        center: 'Brgy. Pag-asa',
        needs: 'Medical',
        status: 'Priority',
        address: 'Brgy. Krus na Ligas',
        registered: 'Jun 11, 2025',
        distributions: [
          { date: '2025-06-14', items: 'Medicine Kit', qty: '1 kit', status: 'Pending' }
        ]
      },
      'BEN-0005': {
        id: 'BEN-0005',
        name: 'Aquino, Rosa G.',
        household: '7',
        center: 'Brgy. Maligaya',
        needs: 'None',
        status: 'Served',
        address: 'Brgy. Maligaya, QC',
        registered: 'Jun 12, 2025',
        distributions: [
          { date: '2025-06-13', items: 'Full Relief Pack', qty: '1 pack', status: 'Completed' }
        ]
      },
      'BEN-0006': {
        id: 'BEN-0006',
        name: 'Garcia, Juan T.',
        household: '2',
        center: 'Brgy. Masaya',
        needs: 'Elderly',
        status: 'Pending',
        address: 'Brgy. Masaya, QC',
        registered: 'Jun 12, 2025',
        distributions: []
      },
      'BEN-0007': {
        id: 'BEN-0007',
        name: 'Villanueva, Clara S.',
        household: '4',
        center: 'Brgy. Pag-asa',
        needs: 'None',
        status: 'Served',
        address: 'Brgy. Pag-asa, QC',
        registered: 'Jun 13, 2025',
        distributions: [
          { date: '2025-06-13', items: 'Rice Pack', qty: '1 pack', status: 'Completed' }
        ]
      },
      'BEN-0008': {
        id: 'BEN-0008',
        name: 'Mendoza, Roberto A.',
        household: '8',
        center: 'Brgy. Maliwanag',
        needs: 'Infant',
        status: 'Priority',
        address: 'Brgy. Maliwanag',
        registered: 'Jun 14, 2025',
        distributions: []
      },
      'BEN-0009': {
        id: 'BEN-0009',
        name: 'Dela Cruz, Nena R.',
        household: '3',
        center: 'Brgy. Maligaya',
        needs: 'Medical',
        status: 'Pending',
        address: 'Brgy. Maligaya, QC',
        registered: 'Jun 14, 2025',
        distributions: []
      },
      'BEN-0010': {
        id: 'BEN-0010',
        name: 'Castillo, Mario F.',
        household: '5',
        center: 'Brgy. Masaya',
        needs: 'None',
        status: 'Served',
        address: 'Brgy. Masaya, QC',
        registered: 'Jun 15, 2025',
        distributions: [
          { date: '2025-06-15', items: 'Family Food Pack', qty: '1 pack', status: 'Completed' }
        ]
      }
    }
  };

  function byId(id) {
    return document.getElementById(id);
  }

  function setText(id, value) {
    const el = byId(id);
    if (el) el.textContent = value;
  }

  function setValue(id, value) {
    const el = byId(id);
    if (el) el.value = value;
  }

  function openModal(id) {
    const modal = byId(id);
    if (modal) modal.classList.add('open');
  }

  function closeModal(id) {
    const modal = byId(id);
    if (modal) modal.classList.remove('open');
  }

  function hideElement(id) {
    const el = byId(id);
    if (el) el.classList.add('hidden');
  }

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
      const roleBtn = document.querySelector('.role-btn.active');
      const role = roleBtn ? roleBtn.textContent.trim() : 'Volunteer';

      if (!username || !password) {
        if (messageEl) {
          messageEl.textContent = 'Please enter your username and password.';
          messageEl.style.color = 'var(--orange)';
        }
        return;
      }

      btn.textContent = 'Signing in...';
      btn.disabled = true;
      if (messageEl) {
        messageEl.textContent = '';
      }

      try {
        const response = await fetch('../api/auth.php?action=login', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'same-origin',
          body: JSON.stringify({ username, password, role })
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok || !data.success) {
          throw new Error(data.message || 'Unable to sign in.');
        }

        window.location.href = data.user?.redirect || 'dashboard.html';
      } catch (error) {
        if (messageEl) {
          messageEl.textContent = error.message || 'Unable to sign in right now.';
          messageEl.style.color = 'var(--orange)';
        }
        btn.textContent = 'Sign In ->';
        btn.disabled = false;
      }
    });
  }

  function openAddModal() {
    openModal('addModal');
  }

  function closeAddModal() {
    closeModal('addModal');
  }

  function saveRecord() {
    closeAddModal();
    alert('Beneficiary saved successfully.');
  }

  function filterTable() {
    const searchInput = byId('searchInput');
    const statusFilter = byId('statusFilter');
    const centerFilter = byId('centerFilter');
    const rows = document.querySelectorAll('#tableBody tr');

    if (!searchInput || !statusFilter || !centerFilter) return;

    const search = searchInput.value.toLowerCase();
    const status = statusFilter.value.toLowerCase();
    const center = centerFilter.value.toLowerCase();

    let visible = 0;

    rows.forEach(function (row) {
      const text = row.textContent.toLowerCase();
      const matchSearch = !search || text.includes(search);
      const matchStatus = !status || text.includes(status);
      const matchCenter = !center || text.includes(center);

      if (matchSearch && matchStatus && matchCenter) {
        row.style.display = '';
        visible++;
      } else {
        row.style.display = 'none';
      }
    });

    setText('recordCount', 'Showing ' + visible + ' of 1,248 records');
  }

  function renderDistributionHistory(distributions) {
    const tbody = byId('distributionHistoryBody');
    if (!tbody) return;

    if (!distributions || !distributions.length) {
      tbody.innerHTML = `
          <tr>
            <td colspan="4" style="text-align:center;color:var(--gray-500);">No distribution records yet.</td>
          </tr>
        `;
      return;
    }

    tbody.innerHTML = distributions.map(function (item) {
      return `
          <tr>
            <td>${item.date}</td>
            <td>${item.items}</td>
            <td>${item.qty}</td>
            <td>${item.status}</td>
          </tr>
        `;
    }).join('');
  }

  function hideEditSection() {
    hideElement('editSection');
    hideElement('editSectionTitle');
    hideElement('saveChangesBtn');
  }

  function hideDistributionForm() {
    hideElement('distributionForm');
    hideElement('distributionFormTitle');
    hideElement('saveDistributionBtn');
  }

  function toggleEditSection() {
    const section = byId('editSection');
    const title = byId('editSectionTitle');
    const button = byId('saveChangesBtn');

    if (!section || !title || !button) return;

    const hidden = section.classList.contains('hidden');

    if (hidden) {
      section.classList.remove('hidden');
      title.classList.remove('hidden');
      button.classList.remove('hidden');
    } else {
      section.classList.add('hidden');
      title.classList.add('hidden');
      button.classList.add('hidden');
    }
  }

  function toggleDistributionForm() {
    const section = byId('distributionForm');
    const title = byId('distributionFormTitle');
    const button = byId('saveDistributionBtn');

    if (!section || !title || !button) return;

    const hidden = section.classList.contains('hidden');

    if (hidden) {
      section.classList.remove('hidden');
      title.classList.remove('hidden');
      button.classList.remove('hidden');
    } else {
      section.classList.add('hidden');
      title.classList.add('hidden');
      button.classList.add('hidden');
    }
  }

  function viewRecord(id) {
    const record = state.beneficiaries[id];
    if (!record) return;

    state.currentBeneficiaryId = id;

    setText('viewModalTitle', record.name);
    setText('detailId', record.id);
    setText('detailName', record.name);
    setText('detailHousehold', record.household);
    setText('detailCenter', record.center);
    setText('detailNeeds', record.needs);
    setText('detailStatus', record.status);
    setText('detailAddress', record.address);
    setText('detailRegistered', record.registered);

    setValue('editName', record.name);
    setValue('editHousehold', record.household);
    setValue('editCenter', record.center);
    setValue('editNeeds', record.needs);
    setValue('editAddress', record.address);

    renderDistributionHistory(record.distributions);
    hideEditSection();
    hideDistributionForm();
    openModal('viewModal');
  }

  function closeViewModal() {
    closeModal('viewModal');
  }

  function saveChanges() {
    if (!state.currentBeneficiaryId) return;

    const record = state.beneficiaries[state.currentBeneficiaryId];
    if (!record) return;

    record.name = byId('editName').value;
    record.household = byId('editHousehold').value;
    record.center = byId('editCenter').value;
    record.needs = byId('editNeeds').value;
    record.address = byId('editAddress').value;

    viewRecord(state.currentBeneficiaryId);
    alert('Beneficiary details updated.');
  }

  function saveDistribution() {
    if (!state.currentBeneficiaryId) return;

    const date = byId('distDate').value;
    const items = byId('distItems').value;
    const qty = byId('distQty').value;
    const status = byId('distStatus').value;

    if (!date || !items || !qty) {
      alert('Please complete the distribution form.');
      return;
    }

    state.beneficiaries[state.currentBeneficiaryId].distributions.unshift({
      date: date,
      items: items,
      qty: qty,
      status: status
    });

    setValue('distDate', '');
    setValue('distItems', '');
    setValue('distQty', '');
    setValue('distStatus', 'Completed');

    viewRecord(state.currentBeneficiaryId);
    alert('Distribution record added.');
  }

  function openEditFromTable(id) {
    viewRecord(id);
    toggleEditSection();
  }

  function setupModalBackdropClose() {
    const addModal = byId('addModal');
    const viewModal = byId('viewModal');

    if (addModal) {
      addModal.addEventListener('click', function (e) {
        if (e.target === addModal) {
          closeAddModal();
        }
      });
    }

    if (viewModal) {
      viewModal.addEventListener('click', function (e) {
        if (e.target === viewModal) {
          closeViewModal();
        }
      });
    }
  }

  function exposeGlobalFunctions() {
    window.openAddModal = openAddModal;
    window.closeAddModal = closeAddModal;
    window.saveRecord = saveRecord;
    window.filterTable = filterTable;
    window.viewRecord = viewRecord;
    window.closeViewModal = closeViewModal;
    window.toggleEditSection = toggleEditSection;
    window.hideEditSection = hideEditSection;
    window.toggleDistributionForm = toggleDistributionForm;
    window.hideDistributionForm = hideDistributionForm;
    window.saveChanges = saveChanges;
    window.saveDistribution = saveDistribution;
    window.openEditFromTable = openEditFromTable;
  }

  function setupAuthGuard() {
    const protectedPages = ['dashboard.html', 'records.html', 'inventory.html', 'evac-centers.html', 'reports.html', 'profile.html', 'backup-restore.html'];
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';

    if (!protectedPages.includes(currentPage)) return;

    fetch('../api/auth.php?action=me', { credentials: 'same-origin' })
      .then(async (response) => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
          window.location.href = 'login.html';
        }
      })
      .catch(() => {
        window.location.href = 'login.html';
      });
  }

  function init() {
    exposeGlobalFunctions();
    setupLoginForm();
    setupAuthGuard();
    setupModalBackdropClose();
  }

  document.addEventListener('DOMContentLoaded', init);
})();

const evacuationCenters = [
  {
    name: 'Brgy. Masaya Evacuation Center',
    shortName: 'Brgy. Masaya',
    status: 'Active',
    contactPerson: 'Ana Reyes',
    contactNumber: '+63 917 111 2233',
    email: 'masaya.center@cdrc.org',
    address: 'Covered Court, Brgy. Masaya, Quezon City',
    beneficiaries: 248
  },
  {
    name: 'Brgy. Maliwanag Evacuation Center',
    shortName: 'Brgy. Maliwanag',
    status: 'Active',
    contactPerson: 'Jose Cruz',
    contactNumber: '+63 918 222 3344',
    email: 'maliwanag.center@cdrc.org',
    address: 'Community Hall, Brgy. Maliwanag, Quezon City',
    beneficiaries: 196
  },
  {
    name: 'Brgy. Pag-asa Evacuation Center',
    shortName: 'Brgy. Pag-asa',
    status: 'Active',
    contactPerson: 'Ben Lopez',
    contactNumber: '+63 919 333 4455',
    email: 'pagasa.center@cdrc.org',
    address: 'Barangay Gymnasium, Brgy. Pag-asa, Quezon City',
    beneficiaries: 210
  },
  {
    name: 'Brgy. Maligaya Evacuation Center',
    shortName: 'Brgy. Maligaya',
    status: 'Active',
    contactPerson: 'Rosa Aquino',
    contactNumber: '+63 920 444 5566',
    email: 'maligaya.center@cdrc.org',
    address: 'Multi-Purpose Hall, Brgy. Maligaya, Quezon City',
    beneficiaries: 172
  },
  {
    name: 'Brgy. Bagong Silang Evacuation Center',
    shortName: 'Brgy. Bagong Silang',
    status: 'Active',
    contactPerson: 'Mario Santos',
    contactNumber: '+63 921 555 6677',
    email: 'bagongsilang.center@cdrc.org',
    address: 'Elementary School Building A, Brgy. Bagong Silang, Quezon City',
    beneficiaries: 204
  },
  {
    name: 'Brgy. San Isidro Evacuation Center',
    shortName: 'Brgy. San Isidro',
    status: 'Active',
    contactPerson: 'Clara Villanueva',
    contactNumber: '+63 922 666 7788',
    email: 'sanisidro.center@cdrc.org',
    address: 'Barangay Session Hall, Brgy. San Isidro, Quezon City',
    beneficiaries: 218
  }
];

function openCenterModal() {
  const modal = document.getElementById('centerModal');
  if (modal) modal.classList.add('open');
}

function closeCenterModal() {
  const modal = document.getElementById('centerModal');
  if (modal) modal.classList.remove('open');
  clearCenterForm();
}

function clearCenterForm() {
  const centerName = document.getElementById('centerName');
  const centerStatus = document.getElementById('centerStatus');
  const centerBeneficiaries = document.getElementById('centerBeneficiaries');
  const centerContactPerson = document.getElementById('centerContactPerson');
  const centerContactNumber = document.getElementById('centerContactNumber');
  const centerEmail = document.getElementById('centerEmail');
  const centerAddress = document.getElementById('centerAddress');

  if (centerName) centerName.value = '';
  if (centerStatus) centerStatus.value = 'Active';
  if (centerBeneficiaries) centerBeneficiaries.value = '';
  if (centerContactPerson) centerContactPerson.value = '';
  if (centerContactNumber) centerContactNumber.value = '';
  if (centerEmail) centerEmail.value = '';
  if (centerAddress) centerAddress.value = '';
}

function addCenter() {
  const name = document.getElementById('centerName')?.value.trim();
  const status = document.getElementById('centerStatus')?.value;
  const beneficiaries = document.getElementById('centerBeneficiaries')?.value.trim();
  const contactPerson = document.getElementById('centerContactPerson')?.value.trim();
  const contactNumber = document.getElementById('centerContactNumber')?.value.trim();
  const email = document.getElementById('centerEmail')?.value.trim();
  const address = document.getElementById('centerAddress')?.value.trim();

  if (!name || !contactPerson || !contactNumber || !address) {
    alert('Please complete the required fields.');
    return;
  }

  const shortName = name.replace(' Evacuation Center', '').trim();

  evacuationCenters.unshift({
    name,
    shortName,
    status,
    contactPerson,
    contactNumber,
    email: email || 'No email provided',
    address,
    beneficiaries: beneficiaries ? Number(beneficiaries) : 0
  });

  renderCenters();
  closeCenterModal();
}

function removeCenter(index) {
  const center = evacuationCenters[index];
  if (!center) return;

  const confirmed = confirm(`Remove ${center.name}?`);
  if (!confirmed) return;

  evacuationCenters.splice(index, 1);
  renderCenters();
}

function viewBeneficiaries(centerName) {
  window.location.href = `records.html?center=${encodeURIComponent(centerName)}`;
}

function updateSummary() {
  const total = evacuationCenters.length;
  const active = evacuationCenters.filter(center => center.status === 'Active').length;

  const totalCenters = document.getElementById('totalCenters');
  const activeCenters = document.getElementById('activeCenters');

  if (totalCenters) totalCenters.textContent = total;
  if (activeCenters) activeCenters.textContent = active;
}

function renderCenters() {
  const grid = document.getElementById('centersGrid');
  const emptyState = document.getElementById('emptyState');

  if (!grid) return;

  if (evacuationCenters.length === 0) {
    grid.innerHTML = '';
    if (emptyState) emptyState.style.display = 'block';
    updateSummary();
    return;
  }

  if (emptyState) emptyState.style.display = 'none';

  grid.innerHTML = evacuationCenters.map((center, index) => `
      <div class="center-card">
        <div class="center-card-header">
          <div>
            <div class="center-name">${center.name}</div>
            <div class="center-sub">Collaborating with CDRC</div>
          </div>
          <span class="badge ${center.status === 'Active' ? 'badge-green' : 'badge-orange'}">${center.status}</span>
        </div>
  
        <div class="center-card-body">
          <div class="info-row">
            <div class="info-label">Contact Person</div>
            <div class="info-value">${center.contactPerson}</div>
          </div>
  
          <div class="info-row">
            <div class="info-label">Contact Details</div>
            <div class="info-value">${center.contactNumber}<br>${center.email}</div>
          </div>
  
          <div class="info-row">
            <div class="info-label">Address</div>
            <div class="info-value">${center.address}</div>
          </div>
  
          <div class="center-stats">
            <div class="stat-box">
              <div class="stat-box-num">${center.beneficiaries}</div>
              <div class="stat-box-label">Beneficiaries</div>
            </div>
            <div class="stat-box">
              <div class="stat-box-num">${center.status}</div>
              <div class="stat-box-label">Current Status</div>
            </div>
          </div>
  
          <div class="card-actions">
            <button class="btn btn-outline" style="padding:8px 12px;font-size:12px;" onclick="viewBeneficiaries('${center.shortName}')">
              View Beneficiaries
            </button>
            <button class="btn btn-outline" style="padding:8px 12px;font-size:12px;color:var(--orange);border-color:rgba(232,75,26,0.25);" onclick="removeCenter(${index})">
              Remove
            </button>
          </div>
        </div>
      </div>
    `).join('');

  updateSummary();
}

function loadCenterFromQuery() {
  const centerFilter = document.getElementById('centerFilter');
  const searchInput = document.getElementById('searchInput');

  if (!centerFilter && !searchInput) return;

  const params = new URLSearchParams(window.location.search);
  const center = params.get('center');

  if (!center) return;

  if (centerFilter) {
    let matched = false;

    for (let i = 0; i < centerFilter.options.length; i++) {
      const option = centerFilter.options[i];
      if (option.value === center || option.text === center) {
        centerFilter.value = option.value || option.text;
        matched = true;
        break;
      }
    }

    if (!matched && searchInput) {
      searchInput.value = center;
    }
  } else if (searchInput) {
    searchInput.value = center;
  }

  if (typeof filterTable === 'function') {
    filterTable();
  }
}

document.addEventListener('DOMContentLoaded', function () {
  const centerModal = document.getElementById('centerModal');
  if (centerModal) {
    centerModal.addEventListener('click', function (e) {
      if (e.target === this) closeCenterModal();
    });
  }

  if (document.getElementById('centersGrid')) {
    renderCenters();
  }

  loadCenterFromQuery();
});
