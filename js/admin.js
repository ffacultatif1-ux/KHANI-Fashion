/**
 * ============================================
 * KHANI Fashion - Administration
 * JavaScript : auth, CRUD, upload image
 * ============================================
 */

const API_BASE = '../api/';

/* ============================
   UTILITAIRES
============================ */

async function api(endpoint, options = {}) {
    try {
        const res = await fetch(API_BASE + endpoint, {
            credentials: 'same-origin',
            ...options
        });
        const raw = await res.text();

        // GitHub Pages ne peut pas exécuter PHP : le serveur renvoie
        // le code source (commentant par "<?php") et non du JSON.
        // On détecte ça pour afficher un message clair.
        if (!raw.trim().startsWith('{') && !raw.trim().startsWith('[')) {
            showToast('Le back-office nécessite un hébergement PHP (InfinityFree). GitHub Pages ne peut pas exécuter PHP.', 'error');
            throw new Error('Hébergement PHP requis pour le back-office');
        }

        const data = JSON.parse(raw);
        if (!res.ok || data.success === false) {
            throw new Error(data.message || 'Erreur API');
        }
        return data;
    } catch (err) {
        console.error('API Error:', err);
        if (!err.message.includes('Hébergement PHP requis')) {
            showToast(err.message, 'error');
        }
        throw err;
    }
}

function formatPrix(n) {
    return Number(n).toLocaleString('fr-FR');
}

function getImageUrl(filename) {
    if (!filename) return '../Images/placeholder.svg';
    if (filename.startsWith('http')) return filename;
    // Chemin explicite : utilisé tel quel
    if (filename.includes('/')) return filename;
    // Fichier uploadé via l'admin (prod_xxx.jpg)
    if (/^(prod_|init_)/.test(filename)) return '../api/uploads/produits/' + filename;
    // Sinon : image livrée avec le site
    return '../Images/' + filename;
}

function getCategorieImageUrl(filename) {
    if (!filename) return '../Images/placeholder.svg';
    if (filename.startsWith('http')) return filename;
    if (/^cat_/.test(filename)) return '../api/uploads/categories/' + filename;
    return '../Images/' + filename;
}

/* ============================
   TOAST
============================ */

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `khani-toast khani-toast--${type}`;
    toast.innerHTML = `
        <i class="fa-solid ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-times-circle' : 'fa-info-circle'}"></i>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add('khani-toast--show'), 10);
    setTimeout(() => {
        toast.classList.remove('khani-toast--show');
        setTimeout(() => toast.remove(), 400);
    }, 3000);
}

/* ============================
   AUTHENTIFICATION
============================ */

async function checkAuth() {
    try {
        const data = await api('auth.php');
        return data.admin;
    } catch (e) {
        // Non authentifié
        window.location.href = 'login.html';
        return null;
    }
}

async function doLogin(e) {
    e.preventDefault();
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const btn = e.target.querySelector('button[type=submit]');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Connexion...';

    try {
        const data = await api('auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        showToast('Connexion réussie 👑');
        setTimeout(() => window.location.href = 'dashboard.html', 500);
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

async function doLogout() {
    try {
        await api('auth.php', { method: 'DELETE' });
    } catch (e) {}
    window.location.href = 'login.html';
}

/* ============================
   DASHBOARD
============================ */

let PRODUITS = [];
let COMMANDES = [];
let CATEGORIES = [];

async function loadDashboard() {
    const admin = await checkAuth();
    if (!admin) return;

    // Afficher nom de l'admin
    const adminNameEl = document.querySelector('[data-admin="nom"]');
    if (adminNameEl) adminNameEl.textContent = admin.nom || admin.username;

    try {
        const [statsData, prodData, cmdData, catData] = await Promise.all([
            api('stats.php'),
            api('produits.php?all=1'),
            api('commandes.php'),
            api('categories.php')
        ]);

        const s = statsData.stats;

        const set = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };

        set('statVentes', formatPrix(s.chiffre_affaires) + ' FCFA');
        set('statCommandes', s.nb_commandes);
        set('statProduits', s.nb_produits);
        set('statVisiteurs', s.nb_visiteurs);
        set('statTopProduit', s.top_produit);

        // Activités récentes (5 dernières commandes)
        const activites = document.getElementById('activitesRecentes');
        if (activites) {
            const last5 = (cmdData.commandes || []).slice(0, 5);
            if (last5.length === 0) {
                activites.innerHTML = '<p class="empty-state">Aucune activité récente.</p>';
            } else {
                activites.innerHTML = last5.map(c => `
                    <div class="activity">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <div>
                            <p><strong>${c.client_nom}</strong> — ${c.reference}</p>
                            <small>${c.client_telephone}</small>
                        </div>
                        <span class="badge badge--${c.statut}">${statutLabel(c.statut)}</span>
                        <span class="activity-date">${formatDate(c.created_at)}</span>
                    </div>
                `).join('');
            }
        }

        // Ventes 7 jours (mini-graphique)
        renderMiniChart(s.ventes_7j || []);

        // Répartition statuts
        renderRepartition(s.repartition || []);

    } catch (e) {
        console.error(e);
    }
}

function statutLabel(s) {
    const labels = {
        en_attente: 'En attente',
        confirmee: 'Confirmée',
        en_livraison: 'En livraison',
        livree: 'Livrée',
        annulee: 'Annulée'
    };
    return labels[s] || s;
}

function formatDate(d) {
    if (!d) return '';
    const date = new Date(d);
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
}

function renderMiniChart(data) {
    const zone = document.getElementById('graphiqueVente');
    if (!zone) return;

    if (data.length === 0) {
        zone.innerHTML = '<p class="empty-state">Aucune vente sur les 7 derniers jours.</p>';
        return;
    }

    const max = Math.max(...data.map(d => Number(d.total)), 1);
    zone.innerHTML = `
        <div class="mini-chart">
            ${data.map(d => `
                <div class="mini-chart-bar" title="${d.jour} : ${formatPrix(d.total)} FCFA">
                    <div class="bar" style="height:${(Number(d.total) / max) * 100}%"></div>
                    <small>${new Date(d.jour).toLocaleDateString('fr-FR', { weekday: 'short' })}</small>
                </div>
            `).join('')}
        </div>
    `;
}

function renderRepartition(data) {
    const zone = document.getElementById('repartitionStatuts');
    if (!zone) return;

    if (data.length === 0) {
        zone.innerHTML = '<p class="empty-state">Aucune commande.</p>';
        return;
    }

    const total = data.reduce((s, d) => s + Number(d.nb), 0);
    const colors = {
        en_attente: '#FFA726',
        confirmee: '#42A5F5',
        en_livraison: '#AB47BC',
        livree: '#66BB6A',
        annulee: '#EF5350'
    };

    zone.innerHTML = data.map(d => {
        const pct = total > 0 ? Math.round((Number(d.nb) / total) * 100) : 0;
        return `
            <div class="repartition-item">
                <div class="repartition-label">
                    <span class="dot" style="background:${colors[d.statut] || '#999'}"></span>
                    ${statutLabel(d.statut)}
                </div>
                <div class="repartition-bar">
                    <div class="repartition-fill" style="width:${pct}%; background:${colors[d.statut] || '#999'}"></div>
                </div>
                <strong>${d.nb}</strong>
            </div>
        `;
    }).join('');
}

/* ============================
   PRODUITS - Admin
============================ */

async function loadProduitsAdmin() {
    const admin = await checkAuth();
    if (!admin) return;

    try {
        const data = await api('produits.php?all=1');
        PRODUITS = data.produits || [];
        renderProduitsAdmin();
        populateCategorieSelect();
    } catch (e) {}
}

function renderProduitsAdmin() {
    const zone = document.getElementById('listeAdminProduits');
    if (!zone) return;

    if (PRODUITS.length === 0) {
        zone.innerHTML = '<p class="empty-state">Aucun produit. Ajoutez-en un ci-dessus.</p>';
        return;
    }

    zone.innerHTML = PRODUITS.map(p => `
        <div class="admin-product">
            <img src="${getImageUrl(p.image)}" alt="${p.nom}" onerror="this.src='../Images/placeholder.svg'">
            <div class="admin-product-info">
                <h3>${p.nom}</h3>
                <p><strong>${formatPrix(p.prix)} FCFA</strong> — Stock : ${p.stock}</p>
                <p>Catégorie : ${p.categorie_nom || 'Aucune'} ${p.actif ? '' : '<span class="badge badge--inactif">Inactif</span>'}</p>
            </div>
            <div class="admin-product-actions">
                <button class="btn-icon btn-edit" onclick="editProduit(${p.id})" title="Modifier">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <button class="btn-icon btn-delete" onclick="deleteProduit(${p.id})" title="Supprimer">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function populateCategorieSelect() {
    api('categories.php').then(data => {
        const sel = document.getElementById('categorieProduit');
        if (!sel) return;
        const current = sel.dataset.value || '';
        sel.innerHTML = '<option value="">Choisir catégorie</option>'
            + (data.categories || []).map(c => `<option value="${c.id}">${c.nom}</option>`).join('');
        if (current) sel.value = current;
    });
}

async function addOrUpdateProduit(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const editId = form.dataset.editId;

    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...';

    try {
        if (editId) {
            formData.append('_method', 'PUT');
            await api(`produits.php?id=${editId}`, {
                method: 'POST',
                body: formData
            });
            showToast('Produit modifié ✅');
        } else {
            await api('produits.php', {
                method: 'POST',
                body: formData
            });
            showToast('Produit ajouté avec succès 👗');
        }

        delete form.dataset.editId;
        form.reset();
        document.getElementById('imagePreview').style.display = 'none';
        btn.innerHTML = '<i class="fa-solid fa-plus"></i> Ajouter le produit';

        await loadProduitsAdmin();
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = editId ? 'Modifier' : 'Ajouter le produit';
    }
}

function editProduit(id) {
    const p = PRODUITS.find(x => x.id === id);
    if (!p) return;

    const form = document.getElementById('produitForm');
    if (!form) return;

    form.dataset.editId = id;
    document.getElementById('nomProduit').value = p.nom;
    document.getElementById('prixProduit').value = p.prix;
    document.getElementById('descriptionProduit').value = p.description || '';
    document.getElementById('stockProduit').value = p.stock;
    document.getElementById('categorieProduit').dataset.value = p.categorie_id || '';
    document.getElementById('categorieProduit').value = p.categorie_id || '';
    document.getElementById('actifProduit').checked = !!p.actif;

    if (p.image) {
        const preview = document.getElementById('imagePreview');
        preview.src = getImageUrl(p.image);
        preview.style.display = 'block';
    }

    const btn = form.querySelector('button[type=submit]');
    btn.innerHTML = '<i class="fa-solid fa-save"></i> Modifier le produit';

    window.scrollTo({ top: form.offsetTop - 100, behavior: 'smooth' });
}

async function deleteProduit(id) {
    if (!confirm('Supprimer ce produit ?')) return;
    try {
        await api(`produits.php?id=${id}`, { method: 'DELETE' });
        showToast('Produit supprimé');
        await loadProduitsAdmin();
    } catch (e) {}
}

function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview) return;
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

/* ============================
   CATÉGORIES - Admin
============================ */

async function loadCategoriesAdmin() {
    const admin = await checkAuth();
    if (!admin) return;

    try {
        const data = await api('categories.php');
        CATEGORIES = data.categories || [];
        renderCategoriesAdmin();
    } catch (e) {}
}

function renderCategoriesAdmin() {
    const zone = document.getElementById('listeCategories');
    if (!zone) return;

    if (CATEGORIES.length === 0) {
        zone.innerHTML = '<p class="empty-state">Aucune catégorie. Ajoutez-en une.</p>';
        return;
    }

    zone.innerHTML = CATEGORIES.map(c => `
        <div class="admin-product">
            <img src="${getCategorieImageUrl(c.image)}" alt="${c.nom}" onerror="this.src='../Images/placeholder.svg'">
            <div class="admin-product-info">
                <h3>${c.nom}</h3>
                ${c.description ? `<p>${c.description}</p>` : ''}
            </div>
            <div class="admin-product-actions">
                <button class="btn-icon btn-delete" onclick="deleteCategorie(${c.id})" title="Supprimer">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

async function addCategorie(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...';

    try {
        await api('categories.php', { method: 'POST', body: formData });
        showToast('Catégorie ajoutée 📁');
        form.reset();
        document.getElementById('catImagePreview').style.display = 'none';
        await loadCategoriesAdmin();
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = 'Ajouter catégorie';
    }
}

async function deleteCategorie(id) {
    if (!confirm('Supprimer cette catégorie ?')) return;
    try {
        await api(`categories.php?id=${id}`, { method: 'DELETE' });
        showToast('Catégorie supprimée');
        await loadCategoriesAdmin();
    } catch (e) {}
}

/* ============================
   COMMANDES - Admin
============================ */

async function loadCommandesAdmin() {
    const admin = await checkAuth();
    if (!admin) return;

    try {
        const data = await api('commandes.php');
        COMMANDES = data.commandes || [];
        renderCommandesAdmin();
    } catch (e) {}
}

function renderCommandesAdmin(filter = 'all') {
    const zone = document.getElementById('listeCommandes');
    if (!zone) return;

    const filtered = filter === 'all' ? COMMANDES : COMMANDES.filter(c => c.statut === filter);

    if (filtered.length === 0) {
        zone.innerHTML = '<p class="empty-state">Aucune commande.</p>';
        return;
    }

    const total = filtered.reduce((s, c) => s + Number(c.total), 0);

    zone.innerHTML = `
        <div class="commandes-header">
            <strong>${filtered.length} commande(s)</strong>
            <strong>Total : ${formatPrix(total)} FCFA</strong>
        </div>
    ` + filtered.map(c => `
        <div class="commande-card">
            <div class="commande-card-head">
                <h3>${c.reference}</h3>
                <span class="badge badge--${c.statut}">${statutLabel(c.statut)}</span>
            </div>
            <div class="commande-card-body">
                <div class="commande-info">
                    <p><i class="fa-solid fa-user"></i> <strong>${c.client_nom}</strong></p>
                    <p><i class="fa-solid fa-phone"></i> ${c.client_telephone}</p>
                    ${c.client_email ? `<p><i class="fa-solid fa-envelope"></i> ${c.client_email}</p>` : ''}
                    ${c.client_adresse ? `<p><i class="fa-solid fa-location-dot"></i> ${c.client_adresse}</p>` : ''}
                    <p><i class="fa-solid fa-calendar"></i> ${formatDate(c.created_at)}</p>
                </div>
                <div class="commande-produits">
                    <h4>Produits :</h4>
                    <ul>
                        ${(c.produits || []).map(p => `
                            <li>${p.nom} × ${p.quantite || 1} = <strong>${formatPrix((Number(p.prix)||0) * (p.quantite||1))} FCFA</strong></li>
                        `).join('')}
                    </ul>
                    <p class="commande-total">Total : <strong>${formatPrix(c.total)} FCFA</strong></p>
                    <p><small>Mode : ${c.mode_paiement || 'livraison'}</small></p>
                </div>
            </div>
            <div class="commande-card-foot">
                <select onchange="changerStatut(${c.id}, this.value)">
                    <option value="">Changer statut...</option>
                    <option value="en_attente">En attente</option>
                    <option value="confirmee">Confirmée</option>
                    <option value="en_livraison">En livraison</option>
                    <option value="livree">Livrée</option>
                    <option value="annulee">Annulée</option>
                </select>
                <button class="btn-icon btn-delete" onclick="deleteCommande(${c.id})">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
    `).join('');
}

async function changerStatut(id, statut) {
    if (!statut) return;
    try {
        const formData = new FormData();
        formData.append('statut', statut);
        await api(`commandes.php?id=${id}`, {
            method: 'POST',
            body: formData,
            headers: { 'X-HTTP-Method-Override': 'PUT' }
        });
        // En PHP, on a autorisé POST aussi pour PUT en vérifiant _method
        showToast('Statut modifié ✅');
        await loadCommandesAdmin();
    } catch (e) {}
}

async function deleteCommande(id) {
    if (!confirm('Supprimer cette commande ?')) return;
    try {
        await api(`commandes.php?id=${id}`, { method: 'DELETE' });
        showToast('Commande supprimée');
        await loadCommandesAdmin();
    } catch (e) {}
}

/* ============================
   PARAMÈTRES - Admin
============================ */

async function loadParametresAdmin() {
    const admin = await checkAuth();
    if (!admin) return;

    try {
        const data = await api('parametres.php');
        const p = data.parametres || {};
        const set = (id, val) => { const el = document.getElementById(id); if (el && val) el.value = val; };
        set('nomBoutique', p.nom_boutique);
        set('emailBoutique', p.email);
        set('telephone1', p.telephone1);
        set('telephone2', p.telephone2);
        set('adresseBoutique', p.adresse);
        set('whatsapp', p.whatsapp);
    } catch (e) {}
}

async function saveParametres(e) {
    e.preventDefault();
    const form = e.target;
    const data = Object.fromEntries(new FormData(form).entries());
    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...';

    try {
        await api('parametres.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        showToast('Paramètres enregistrés ✅');
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = 'Enregistrer les paramètres';
    }
}

/* ============================
   STATS - Admin
============================ */

async function loadStats() {
    const admin = await checkAuth();
    if (!admin) return;

    try {
        const data = await api('stats.php');
        const s = data.stats;
        const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        set('statVentes', formatPrix(s.chiffre_affaires) + ' FCFA');
        set('statCommandes', s.nb_commandes);
        set('statVisiteurs', s.nb_visiteurs);
        set('statTopProduit', s.top_produit);

        renderMiniChart(s.ventes_7j || []);
        renderRepartition(s.repartition || []);
    } catch (e) {}
}

/* ============================
   INIT GLOBAL
============================ */

document.addEventListener('DOMContentLoaded', () => {
    // Détection d'un hébergement statique (GitHub Pages) : PHP indisponible
    // => on affiche un bandeau d'information clair sur la page de login.
    const host = (window.location && window.location.hostname) || '';
    const isGithubPages = host.endsWith('github.io');
    const ghWarn = document.getElementById('ghPagesWarning');
    if (ghWarn && isGithubPages) {
        ghWarn.style.display = 'block';
    }

    // Login form
    const loginForm = document.getElementById('loginForm');
    if (loginForm) loginForm.addEventListener('submit', doLogin);

    // Logout button
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) logoutBtn.addEventListener('click', doLogout);

    // Page courante
    const page = document.body.dataset.page;

    if (page === 'dashboard') loadDashboard();
    else if (page === 'produits') {
        checkAuth().then(() => {
            loadProduitsAdmin();
            const form = document.getElementById('produitForm');
            if (form) form.addEventListener('submit', addOrUpdateProduit);
        });
    }
    else if (page === 'categories') {
        checkAuth().then(() => {
            loadCategoriesAdmin();
            const form = document.getElementById('categorieForm');
            if (form) form.addEventListener('submit', addCategorie);
        });
    }
    else if (page === 'commandes') {
        checkAuth().then(() => loadCommandesAdmin());
    }
    else if (page === 'statistiques') {
        checkAuth().then(() => loadStats());
    }
    else if (page === 'parametres') {
        checkAuth().then(() => {
            loadParametresAdmin();
            const form = document.getElementById('parametreForm');
            if (form) form.addEventListener('submit', saveParametres);
        });
    }
});
