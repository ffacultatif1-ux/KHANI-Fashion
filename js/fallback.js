/**
 * =========================================================
 * KHANI Fashion - Jeu de données de secours (offline)
 * =========================================================
 *
 * Utilisé UNIQUEMENT quand :
 *   - Supabase n'est pas encore configuré (js/config.js), OU
 *   - l'API REST Supabase échoue, OU
 *   - l'API PHP locale est indisponible.
 *
 * Ces données reproduisent les 18 produits créés par
 * install.php à partir du dossier Images/.
 */
const FALLBACK_DATA = {
    categories: [
        { id: 1, nom: 'Robes', image: null, description: 'Robes africaines élégantes' },
        { id: 2, nom: 'Pagnes', image: null, description: 'Pagnes wax de qualité' },
        { id: 3, nom: 'Ensembles', image: null, description: 'Ensembles modernes' },
        { id: 4, nom: 'Accessoires', image: null, description: 'Accessoires de mode' }
    ],

    produits: [
        { id: 1,  nom: 'Robe Africaine 1',       description: 'Magnifique article de la collection KHANI Fashion.', prix: 30000, image: 'Robe Africaine1.jpg',  categorie_id: 1, stock: 10, actif: 1 },
        { id: 2,  nom: 'Robe Africaine 2',       description: 'Magnifique article de la collection KHANI Fashion.', prix: 30000, image: 'Robe Africaine2.jpg',  categorie_id: 1, stock: 10, actif: 1 },
        { id: 3,  nom: 'Robe Africaine 3',       description: 'Magnifique article de la collection KHANI Fashion.', prix: 30000, image: 'Robe Africaine3.jpg',  categorie_id: 1, stock: 10, actif: 1 },
        { id: 4,  nom: 'Robe Africaine 4',       description: 'Magnifique article de la collection KHANI Fashion.', prix: 30000, image: 'Robe Africaine4.jpg',  categorie_id: 1, stock: 10, actif: 1 },
        { id: 5,  nom: 'Robe Africaine 5',       description: 'Magnifique article de la collection KHANI Fashion.', prix: 30000, image: 'Robe Africaine5.jpg',  categorie_id: 1, stock: 10, actif: 1 },
        { id: 6,  nom: 'Robe Africaine 6',       description: 'Magnifique article de la collection KHANI Fashion.', prix: 30000, image: 'Robe Africaine6.jpg',  categorie_id: 1, stock: 10, actif: 1 },
        { id: 7,  nom: 'Robe Africaine Noire',   description: 'Magnifique article de la collection KHANI Fashion.', prix: 30000, image: 'Robe Africaine noire.jpg', categorie_id: 1, stock: 10, actif: 1 },
        { id: 8,  nom: 'Ensemble 1',             description: 'Magnifique article de la collection KHANI Fashion.', prix: 25000, image: 'Ensemble 1.jpg',     categorie_id: 3, stock: 10, actif: 1 },
        { id: 9,  nom: 'Ensemble 2',             description: 'Magnifique article de la collection KHANI Fashion.', prix: 25000, image: 'Ensemble 2.jpg',     categorie_id: 3, stock: 10, actif: 1 },
        { id: 10, nom: 'Ensemble 3',             description: 'Magnifique article de la collection KHANI Fashion.', prix: 25000, image: 'Ensemble3.jpg',      categorie_id: 3, stock: 10, actif: 1 },
        { id: 11, nom: 'Ensemble 4',             description: 'Magnifique article de la collection KHANI Fashion.', prix: 25000, image: 'Ensemble4.jpg',      categorie_id: 3, stock: 10, actif: 1 },
        { id: 12, nom: 'Ensemble 5',             description: 'Magnifique article de la collection KHANI Fashion.', prix: 25000, image: 'Ensemble5.jpg',      categorie_id: 3, stock: 10, actif: 1 },
        { id: 13, nom: 'Pagne Wax 1',            description: 'Magnifique article de la collection KHANI Fashion.', prix: 15000, image: 'Pagne Wax1.jpg',      categorie_id: 2, stock: 10, actif: 1 },
        { id: 14, nom: 'Pagne Wax 2',            description: 'Magnifique article de la collection KHANI Fashion.', prix: 15000, image: 'Pagne Wax2.jpg',      categorie_id: 2, stock: 10, actif: 1 },
        { id: 15, nom: 'Pagne Wax 3',            description: 'Magnifique article de la collection KHANI Fashion.', prix: 15000, image: 'Pagne Wax3.jpg',      categorie_id: 2, stock: 10, actif: 1 },
        { id: 16, nom: 'Pagne Wax 4',            description: 'Magnifique article de la collection KHANI Fashion.', prix: 15000, image: 'Pagne Wax4.jpg',      categorie_id: 2, stock: 10, actif: 1 },
        { id: 17, nom: 'Pagne Wax 5',            description: 'Magnifique article de la collection KHANI Fashion.', prix: 15000, image: 'Pagne Wax5.jpg',      categorie_id: 2, stock: 10, actif: 1 },
        { id: 18, nom: 'Pagne Wax 6',            description: 'Magnifique article de la collection KHANI Fashion.', prix: 15000, image: 'Pagne Wax6.jpg',      categorie_id: 2, stock: 10, actif: 1 }
    ],

    parametres: {
        nom_boutique: 'KHANI Fashion',
        email: 'khanihenoc8@gmail.com',
        telephone1: '+242 06 176 32 04',
        telephone2: '+242 06 526 92 13',
        adresse: 'Brazzaville, Congo',
        whatsapp: '242061763204',
        devise: 'FCFA'
    }
};