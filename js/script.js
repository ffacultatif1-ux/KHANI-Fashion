/**
 * ============================================
 * KHANI Fashion - JavaScript principal (Frontend)
 * Version refactorisée avec API PHP/MySQL
 * ============================================
 */

const API_BASE = 'api/';

/* ============================
   UTILITAIRES
============================ */

async function api(endpoint, options = {}) {
    try {
        const res = await fetch(API_BASE + endpoint, options);
        const data = await res.json();
        if (!res.ok || data.success === false) {
            throw new Error(data.message || 'Erreur API');
        }
        return data;
    } catch (err) {
        console.error('API Error:', err);
        showToast(err.message, 'error');
        throw err;
    }
}

function formatPrix(n) {
    return Number(n).toLocaleString('fr-FR');
}

function getImageUrl(filename) {
    if (!filename) return 'Images/placeholder.svg';
    if (filename.startsWith('http')) return filename;
    // Chemin explicite (ex: "api/uploads/...") : utilisé tel quel
    if (filename.includes('/')) return filename;
    // Fichier uploadé via l'admin (prod_xxx.jpg) : dossier api/uploads/
    if (/^(prod_|init_)/.test(filename)) return API_BASE + 'uploads/produits/' + filename;
    // Sinon : image livrée avec le site (ex: "Ensemble 1.jpg" -> Images/)
    return 'Images/' + filename;
}

function getCategorieImageUrl(filename) {
    if (!filename) return 'Images/placeholder.svg';
    if (filename.startsWith('http')) return filename;
    if (/^cat_/.test(filename)) return API_BASE + 'uploads/categories/' + filename;
    return 'Images/' + filename;
}

/* ============================
   TOAST (notifications)
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
   COMPTEUR VISITEUR
============================ */

(function trackVisitor() {
    // 1) Supabase direct (site GitHub Pages)
    if (typeof SUPABASE !== 'undefined' && SUPABASE.isConfigured()) {
        SUPABASE.trackVisiteur().catch(() => {});
    }
    // 2) API PHP locale (serveur avec PHP)
    api('stats.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    }).catch(() => {});
})();

/* ============================
   PARAMÈTRES BOUTIQUE (footer / contact)
============================ */

let PARAMETRES = {};

async function loadParametres() {
    // 1) Supabase direct (site GitHub Pages)
    try {
        if (typeof SUPABASE !== 'undefined' && SUPABASE.isConfigured()) {
            const p = await SUPABASE.getParametres();
            if (p && Object.keys(p).length > 0) {
                PARAMETRES = p;
                updateContactInfo();
                return;
            }
        }
    } catch (e) {
        console.warn('Supabase paramètres indisponibles');
    }
    // 2) API PHP locale (serveur avec PHP)
    try {
        const data = await api('parametres.php');
        PARAMETRES = data.parametres || {};
        // Mise à jour du footer et contact si les éléments existent
        updateContactInfo();
    } catch (e) {
        console.warn('Paramètres non chargés');
    }
    // 3) Mode de secours statique
    if (typeof FALLBACK_DATA !== 'undefined' && PARAMETRES && Object.keys(PARAMETRES).length === 0) {
        PARAMETRES = FALLBACK_DATA.parametres || {};
        updateContactInfo();
    }
}

function updateContactInfo() {
    document.querySelectorAll('[data-contact="phone1"]').forEach(el => {
        if (PARAMETRES.telephone1) el.textContent = PARAMETRES.telephone1;
    });
    document.querySelectorAll('[data-contact="phone2"]').forEach(el => {
        if (PARAMETRES.telephone2) el.textContent = PARAMETRES.telephone2;
    });
    document.querySelectorAll('[data-contact="email"]').forEach(el => {
        if (PARAMETRES.email) el.textContent = PARAMETRES.email;
    });
    document.querySelectorAll('[data-contact="adresse"]').forEach(el => {
        if (PARAMETRES.adresse) el.textContent = PARAMETRES.adresse;
    });
}

/* ============================
   PRODUITS
============================ */

let PRODUITS = [];
let CATEGORIES = [];

async function loadProduits() {
    // 1) Supabase direct (site GitHub Pages)
    try {
        if (typeof SUPABASE !== 'undefined' && SUPABASE.isConfigured()) {
            const prods = await SUPABASE.getProduits();
            if (prods && prods.length > 0) {
                PRODUITS = prods;
                return;
            }
        }
    } catch (e) {
        console.warn('Supabase produits indisponibles');
    }
    // 2) API PHP locale (serveur avec PHP)
    try {
        const data = await api('produits.php');
        PRODUITS = data.produits || [];
        return;
    } catch (e) {
        PRODUITS = [];
    }
    // 3) Mode de secours statique
    if (PRODUITS.length === 0 && typeof FALLBACK_DATA !== 'undefined') {
        PRODUITS = FALLBACK_DATA.produits || [];
    }
}

async function loadCategories() {
    // 1) Supabase direct (site GitHub Pages)
    try {
        if (typeof SUPABASE !== 'undefined' && SUPABASE.isConfigured()) {
            const cats = await SUPABASE.getCategories();
            if (cats && cats.length > 0) {
                CATEGORIES = cats;
                return;
            }
        }
    } catch (e) {
        console.warn('Supabase catégories indisponibles');
    }
    // 2) API PHP locale (serveur avec PHP)
    try {
        const data = await api('categories.php');
        CATEGORIES = data.categories || [];
        return;
    } catch (e) {
        CATEGORIES = [];
    }
    // 3) Mode de secours statique
    if (CATEGORIES.length === 0 && typeof FALLBACK_DATA !== 'undefined') {
        CATEGORIES = FALLBACK_DATA.categories || [];
    }
}

/* ============================
   PANIER (unifié)
============================ */

const PANIER_KEY = 'khani_panier_v2';

function getPanier() {
    try {
        return JSON.parse(localStorage.getItem(PANIER_KEY)) || [];
    } catch { return []; }
}

function savePanier(p) {
    localStorage.setItem(PANIER_KEY, JSON.stringify(p));
    updateCartCount();
}

function addToCart(produit) {
    const panier = getPanier();
    const existant = panier.find(p => p.id === produit.id);
    if (existant) {
        existant.quantite++;
    } else {
        panier.push({
            id: produit.id,
            nom: produit.nom,
            prix: Number(produit.prix),
            image: produit.image,
            quantite: 1
        });
    }
    savePanier(panier);
    showToast(`${produit.nom} ajouté au panier 🛒`);
}

function removeFromCart(index) {
    const panier = getPanier();
    panier.splice(index, 1);
    savePanier(panier);
    renderCart();
}

function changeQty(index, delta) {
    const panier = getPanier();
    panier[index].quantite += delta;
    if (panier[index].quantite <= 0) {
        panier.splice(index, 1);
    }
    savePanier(panier);
    renderCart();
}

function getCartTotal() {
    return getPanier().reduce((sum, p) => sum + Number(p.prix) * p.quantite, 0);
}

function getCartCount() {
    return getPanier().reduce((sum, p) => sum + p.quantite, 0);
}

function updateCartCount() {
    document.querySelectorAll('#nombrePanier').forEach(el => {
        el.textContent = getCartCount();
    });
}

function renderCart() {
    // Le total doit s'afficher aussi sur commande.html (qui n'a pas de zone panier)
    const totalZone = document.getElementById('totalPrix');
    if (totalZone) totalZone.textContent = formatPrix(getCartTotal());
    const zone = document.getElementById('contenuPanier');
    if (!zone) return;

    const panier = getPanier();
    zone.innerHTML = '';

    if (panier.length === 0) {
        zone.innerHTML = '<p class="panier-vide">Votre panier est vide 🛒</p>';
    } else {
        panier.forEach((p, i) => {
            zone.innerHTML += `
                <div class="article-panier">
                    <img src="${getImageUrl(p.image)}" alt="${p.nom}" onerror="this.src='Images/placeholder.svg'">
                    <div class="article-info">
                        <h4>${p.nom}</h4>
                        <p class="article-prix">${formatPrix(p.prix)} FCFA</p>
                        <div class="article-qty">
                            <button onclick="changeQty(${i}, -1)">−</button>
                            <span>${p.quantite}</span>
                            <button onclick="changeQty(${i}, 1)">+</button>
                        </div>
                    </div>
                    <button class="article-del" onclick="removeFromCart(${i})" title="Supprimer">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            `;
        });
    }

    updateCartCount();
}

/* ============================
   PAGE D'ACCUEIL - produits vedettes
============================ */

async function renderFeaturedProducts() {
    const zone = document.querySelector('.products');
    if (!zone) return;

    await loadProduits();

    if (PRODUITS.length === 0) {
        zone.innerHTML = '<p class="empty-state">Aucun produit disponible pour le moment.</p>';
        return;
    }

    // On prend les 6 premiers actifs
    const featured = PRODUITS.slice(0, 6);
    zone.innerHTML = featured.map(p => `
        <div class="product">
            <img src="${getImageUrl(p.image)}" alt="${p.nom}" onerror="this.src='Images/placeholder.svg'">
            <h3>${p.nom}</h3>
            <p class="price">${formatPrix(p.prix)} FCFA</p>
            <button onclick="addToCartFromCard(this, ${p.id})">
                <i class="fa-solid fa-cart-plus"></i> Ajouter au panier
            </button>
        </div>
    `).join('');
}

function addToCartFromCard(btn, id) {
    const produit = PRODUITS.find(p => p.id === id);
    if (produit) addToCart(produit);
}

/* ============================
   PAGE BOUTIQUE - liste + filtres
============================ */

async function renderBoutique() {
    const zone = document.getElementById('listeProduits');
    if (!zone) return;

    await Promise.all([loadProduits(), loadCategories()]);

    // Remplir le filtre catégories
    const filtre = document.getElementById('filtreCategorie');
    if (filtre && CATEGORIES.length > 0) {
        filtre.innerHTML = '<option value="all">Toutes les catégories</option>'
            + CATEGORIES.map(c => `<option value="${c.id}">${c.nom}</option>`).join('');
        filtre.addEventListener('change', () => filterBoutique());
    }

    const recherche = document.getElementById('rechercheProduit');
    if (recherche) recherche.addEventListener('input', () => filterBoutique());

    filterBoutique();
}

function filterBoutique() {
    const recherche = document.getElementById('rechercheProduit');
    const filtre = document.getElementById('filtreCategorie');
    const term = (recherche?.value || '').toLowerCase();
    const catId = filtre?.value || 'all';

    const filtered = PRODUITS.filter(p => {
        const matchTerm = !term || p.nom.toLowerCase().includes(term);
        const matchCat = catId === 'all' || String(p.categorie_id) === catId;
        return matchTerm && matchCat;
    });

    const zone = document.getElementById('listeProduits');
    if (!zone) return;

    if (filtered.length === 0) {
        zone.innerHTML = '<p class="empty-state">Aucun produit trouvé.</p>';
        return;
    }

    zone.innerHTML = filtered.map(p => `
        <div class="produit-card">
            <img src="${getImageUrl(p.image)}" alt="${p.nom}" onerror="this.src='Images/placeholder.svg'">
            <h3>${p.nom}</h3>
            ${p.description ? `<p>${p.description}</p>` : ''}
            <strong>${formatPrix(p.prix)} FCFA</strong>
            <button onclick="addToCartFromCard(this, ${p.id})">
                <i class="fa-solid fa-cart-plus"></i> Ajouter au panier
            </button>
        </div>
    `).join('');
}

/* ============================
   COMMANDE WHATSAPP (depuis panier)
============================ */

async function commanderWhatsApp(e) {
    if (e && e.preventDefault) e.preventDefault();
    const panier = getPanier();
    if (panier.length === 0) {
        showToast('Votre panier est vide', 'error');
        return;
    }

    const client = {
        nom: document.getElementById('nomClient')?.value || '',
        telephone: document.getElementById('telephoneClient')?.value || '',
        adresse: document.getElementById('adresseClient')?.value || '',
        email: document.getElementById('emailClient')?.value || '',
    };
    const paiement = document.getElementById('paiementClient')?.value || 'livraison';

    // Lien WhatsApp de secours (si l'enregistrement serveur échoue)
    const whatsappUrlLocal = () => {
        const numero = (PARAMETRES.whatsapp || '242061763204').replace(/\D/g, '');
        let msg = 'Bonjour KHANI Fashion';
        msg += '%0A%0A';
        msg += 'Je souhaite commander :';
        msg += '%0A%0A';
        panier.forEach(p => {
            msg += `- ${p.nom} x ${p.quantite} = ${formatPrix(p.prix * p.quantite)} FCFA%0A`;
        });
        msg += `%0ATotal : ${formatPrix(getCartTotal())} FCFA`;
        return `https://wa.me/${numero}?text=${msg}`;
    };

    try {
        // Enregistrer la commande côté serveur
        let urlWhats = null;

        // 1) Supabase direct (site GitHub Pages)
        if (typeof SUPABASE !== 'undefined' && SUPABASE.isConfigured()) {
            try {
                await SUPABASE.createCommande(client, panier, getCartTotal(), paiement, '');
                urlWhats = whatsappUrlLocal();
            } catch (eSup) {
                console.warn('Supabase commande impossible, tentative PHP');
            }
        }

        // 2) API PHP locale (serveur avec PHP)
        if (!urlWhats) {
            const data = await api('commandes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    client: client,
                    paiement: paiement,
                    produits: panier,
                    total: getCartTotal(),
                })
            });
            urlWhats = data.whatsapp_url || whatsappUrlLocal();
        }

        // Vider le panier
        savePanier([]);

        // Ouvrir WhatsApp
        if (urlWhats) {
            showToast('Commande enregistrée 🎉 Redirection WhatsApp...');
            setTimeout(() => window.open(urlWhats, '_blank'), 800);
        } else {
            showToast('Commande enregistrée 🎉');
            setTimeout(() => window.location.href = 'index.html', 1500);
        }
    } catch (e) {
        // En cas d'erreur API, on génère quand même un lien WhatsApp local
        window.open(whatsappUrlLocal(), '_blank');
    }
}

/* ============================
   COMMANDE DEPUIS INDEX.HTML (formulaire contact)
============================ */

async function envoyerCommandeRapide(e) {
    e.preventDefault();
    const form = e.target;
    const fd = new FormData(form);
    const produit = fd.get('produit');
    const quantite = parseInt(fd.get('quantite') || '1');

    // Trouver le produit par nom
    if (!PRODUITS.length) await loadProduits();
    const p = PRODUITS.find(x => x.nom.toLowerCase() === (produit || '').toLowerCase());

    const produits = p ? [{ id: p.id, nom: p.nom, prix: Number(p.prix), quantite }] : [];
    const total = produits.reduce((s, x) => s + x.prix * x.quantite, 0);

    const client = {
        nom: fd.get('nom'),
        telephone: fd.get('telephone'),
        email: fd.get('email'),
        adresse: fd.get('ville'),
    };
    const produitsCommande = produits.length ? produits : [{ nom: produit, prix: 0, quantite }];

    try {
        let enregistree = false;

        // 1) Supabase direct (site GitHub Pages)
        if (typeof SUPABASE !== 'undefined' && SUPABASE.isConfigured()) {
            try {
                await SUPABASE.createCommande(client, produitsCommande, total || 0, 'livraison', fd.get('message'));
                enregistree = true;
            } catch (eSup) {
                console.warn('Supabase commande rapide impossible, tentative PHP');
            }
        }

        // 2) API PHP locale (serveur avec PHP)
        if (!enregistree) {
            await api('commandes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    client: client,
                    paiement: 'livraison',
                    produits: produitsCommande,
                    total: total || 0,
                    notes: fd.get('message'),
                })
            });
        }
        showToast('Commande envoyée avec succès 🎉');
        form.reset();
    } catch (e) {
        showToast('Erreur lors de l\'envoi. Réessayez.', 'error');
    }
}

/* ============================
   NEWSLETTER
============================ */

function inscrireNewsletter(e) {
    e.preventDefault();
    const email = e.target.querySelector('input[type=email]').value;
    if (!email) return;
    // Stockage local en attendant un vrai système
    let liste = JSON.parse(localStorage.getItem('khani_newsletter') || '[]');
    if (!liste.includes(email)) liste.push(email);
    localStorage.setItem('khani_newsletter', JSON.stringify(liste));
    showToast('Merci ! Vous êtes inscrit à la newsletter 💌');
    e.target.reset();
}

/* ============================
   INIT GLOBAL
============================ */

document.addEventListener('DOMContentLoaded', () => {
    loadParametres();
    updateCartCount();

    // Page d'accueil : produits vedettes
    renderFeaturedProducts();

    // Page boutique
    renderBoutique();

    // Page panier
    renderCart();

    // Formulaire de commande (index.html)
    const orderForm = document.getElementById('orderForm');
    if (orderForm) orderForm.addEventListener('submit', envoyerCommandeRapide);

    // Formulaire de finalisation (commande.html)
    const formCommande = document.getElementById('formCommande');
    if (formCommande) formCommande.addEventListener('submit', commanderWhatsApp);

    // Bouton commander WhatsApp
    document.querySelectorAll('[data-action="commander-whatsapp"]').forEach(btn => {
        btn.addEventListener('click', commanderWhatsApp);
    });

    // Newsletter
    document.querySelectorAll('.newsletter form').forEach(f => {
        f.addEventListener('submit', inscrireNewsletter);
    });

    // Bouton panier (ouvre la page panier)
    document.querySelectorAll('.panier, .fa-cart-shopping').forEach(el => {
        if (el.closest('a')) return; // déjà un lien
    });
});
