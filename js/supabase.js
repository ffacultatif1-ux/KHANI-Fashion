/**
 * =========================================================
 * KHANI Fashion - Client Supabase (PostgREST)
 * =========================================================
 *
 * Permet au site GitHub Pages de communiquer DIRECTEMENT
 * avec la base Supabase, sans passer par du PHP.
 *
 * Utilise la clé "anon" publique (conçue pour le navigateur).
 *
 * Si la clé n'est pas configurée (js/config.js), toutes ces
 * fonctions échouent rapidement et le site bascule sur le
 * mode de secours js/fallback.js.
 */
const SUPABASE = (() => {

    const cfg = () => (typeof KHANI_CONFIG !== 'undefined' && KHANI_CONFIG) || {};

    /**
     * true si l'URL et la clé anon sont renseignées.
     */
    function isConfigured() {
        const c = cfg();
        return !!(c && c.supabaseUrl && c.supabaseUrl.startsWith('http') && c.supabaseAnonKey);
    }

    /**
     * Appel brut à l'API REST de Supabase (PostgREST).
     */
    async function request(method, path, body) {
        const c = cfg();
        const res = await fetch(c.supabaseUrl + '/rest/v1/' + path, {
            method: method,
            headers: {
                'apikey': c.supabaseAnonKey,
                'Authorization': 'Bearer ' + c.supabaseAnonKey,
                'Content-Type': 'application/json'
            },
            body: body ? JSON.stringify(body) : undefined
        });
        if (!res.ok) {
            throw new Error('Supabase HTTP ' + res.status);
        }
        if (res.status === 204) return null;
        return res.json();
    }

    /* -------------------------------------------------
       LECTURES PUBLIQUES
    ------------------------------------------------- */

    /** Liste des produits actifs (mêmes champs que l'API PHP). */
    async function getProduits() {
        const rows = await request('GET',
            'produits?select=id,nom,description,prix,image,categorie_id,stock,actif&actif=eq.1&order=created_at.desc'
        );
        return rows || [];
    }

    /** Liste des catégories. */
    async function getCategories() {
        const rows = await request('GET',
            'categories?select=id,nom,image,description&order=nom'
        );
        return rows || [];
    }

    /** Paramètres boutique (mode clé/valeur -> objet). */
    async function getParametres() {
        const rows = await request('GET', 'parametres?select=cle,valeur');
        if (!rows) return {};
        const out = {};
        rows.forEach(r => { out[r.cle] = r.valeur; });
        return out;
    }

    /* -------------------------------------------------
       ÉCRITURES PUBLIQUES
    ------------------------------------------------- */

    /**
     * Enregistre une commande (retourne la référence).
     * Nécessite la politique RLS "insertion_publique_commandes".
     */
    async function createCommande(client, produits, total, paiement, notes) {
        const parts = new Date().toISOString().slice(0, 10).replace(/-/g, '');
        const rand = Math.random().toString(16).slice(2, 6).toUpperCase();
        const reference = 'KHN-' + parts + '-' + rand;
        const row = {
            reference: reference,
            client_nom: (client && client.nom) || '',
            client_telephone: (client && client.telephone) || '',
            client_email: (client && client.email) || '',
            client_adresse: (client && client.adresse) || '',
            ville: (client && client.ville) || '',
            mode_paiement: paiement || 'livraison',
            produits_json: JSON.stringify(produits || []),
            total: Number(total) || 0,
            notes: notes || ''
        };
        await request('POST', 'commandes', row);
        return reference;
    }

    /** Enregistre une visite (compteur). */
    async function trackVisiteur() {
        await request('POST', 'visiteurs', {
            ip: null,
            page: (typeof location !== 'undefined' ? location.pathname : ''),
            user_agent: (typeof navigator !== 'undefined' ? String(navigator.userAgent).slice(0, 500) : '')
        });
    }

    return {
        isConfigured,
        getProduits,
        getCategories,
        getParametres,
        createCommande,
        trackVisiteur
    };
})();