// js/api.js — Centralized API functions

const API_ROOT = '../api';

async function apiFetch(endpoint, options = {}) {
    const url = endpoint.startsWith('http') || endpoint.startsWith('/')
        ? endpoint
        : `${API_ROOT}/${endpoint.replace(/^\/+/, '')}`;

    const mergedOptions = {
        credentials: 'same-origin',
        ...options,
        headers: {
            'Content-Type': 'application/json',
            ...(options.headers || {}),
        },
    };

    const response = await fetch(url, mergedOptions);
    const data = await response.json();
    
    if (!response.ok) {
        throw new Error(data.message || 'Request failed');
    }
    
    return data;
}

async function apiGet(endpoint) {
    return apiFetch(endpoint, { method: 'GET' });
}

async function apiPost(endpoint, body) {
    return apiFetch(endpoint, {
        method: 'POST',
        body: JSON.stringify(body)
    });
}

// Auth functions
async function login(username, password) {
    return apiPost('auth.php?action=login', { username, password });
}

async function logout() {
    return apiPost('auth.php?action=logout', {});
}

async function getCurrentUser() {
    return apiGet('auth.php?action=me');
}

// Beneficiaries
async function getBeneficiaries(params = {}) {
    const query = new URLSearchParams(params).toString();
    return apiGet(`beneficiaries.php?action=list&${query}`);
}

async function getBeneficiary(id) {
    return apiGet(`beneficiaries.php?action=get&id=${id}`);
}

async function createBeneficiary(data) {
    return apiPost('beneficiaries.php?action=create', data);
}

async function updateBeneficiary(id, data) {
    return apiPost(`beneficiaries.php?action=update&id=${id}`, data);
}

async function deleteBeneficiary(id) {
    return apiPost(`beneficiaries.php?action=delete&id=${id}`, {});
}

async function getBeneficiaryStats() {
    return apiGet('beneficiaries.php?action=stats');
}

// Inventory
async function getInventory(params = {}) {
    const query = new URLSearchParams(params).toString();
    return apiGet(`inventory.php?action=list&${query}`);
}

async function getCategories() {
    return apiGet('inventory.php?action=categories');
}

async function stockIn(itemId, quantity, source) {
    return apiPost('inventory.php?action=stock_in', {
        item_id: itemId,
        quantity: quantity,
        reference_note: source,
        transaction_date: new Date().toISOString().split('T')[0]
    });
}

async function stockOut(itemId, quantity, note) {
    return apiPost('inventory.php?action=stock_out', {
        item_id: itemId,
        quantity: quantity,
        reference_note: note,
        transaction_date: new Date().toISOString().split('T')[0]
    });
}

async function getLowStock() {
    return apiGet('inventory.php?action=low_stock');
}

// Distributions
async function getDistributions(params = {}) {
    const query = new URLSearchParams(params).toString();
    return apiGet(`distributions.php?action=list&${query}`);
}

async function createDistribution(data) {
    return apiPost('distributions.php?action=create', data);
}

// Centers
async function getCenters() {
    return apiGet('centers.php?action=list');
}

async function createCenter(data) {
    return apiPost('centers.php?action=create', data);
}

async function deleteCenter(id) {
    return apiPost(`centers.php?action=delete&id=${id}`, {});
}

// Dashboard
async function getDashboardKPIs() {
    return apiGet('dashboard.php?action=kpis');
}

async function getActivityFeed() {
    return apiGet('dashboard.php?action=activity');
}

// Reports
async function getDistributionReport(dateFrom, dateTo, centerId = 0) {
    return apiGet(`reports.php?action=distribution&date_from=${dateFrom}&date_to=${dateTo}&center_id=${centerId}`);
}

async function getBeneficiaryReport(centerId = 0) {
    return apiGet(`reports.php?action=beneficiary&center_id=${centerId}`);
}

async function getInventoryReport() {
    return apiGet('reports.php?action=inventory');
}

async function getReportSummary(dateFrom, dateTo) {
    return apiGet(`reports.php?action=summary&date_from=${dateFrom}&date_to=${dateTo}`);
}

// Special needs types
async function getSpecialNeeds() {
    return apiGet('beneficiaries.php?action=special_needs');
}

// Export for use
window.API = {
    apiFetch, apiGet, apiPost,
    login, logout, getCurrentUser,
    getBeneficiaries, getBeneficiary, createBeneficiary, updateBeneficiary, deleteBeneficiary, getBeneficiaryStats,
    getInventory, getCategories, stockIn, stockOut, getLowStock,
    getDistributions, createDistribution,
    getCenters, createCenter, deleteCenter,
    getDashboardKPIs, getActivityFeed,
    getDistributionReport, getBeneficiaryReport, getInventoryReport, getReportSummary,
    getSpecialNeeds
};
