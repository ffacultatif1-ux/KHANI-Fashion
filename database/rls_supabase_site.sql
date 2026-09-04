-- =========================================================
-- KHANI Fashion - Politiques de sécurité (RLS) pour Supabase
-- =========================================================
-- À exécuter DANS Supabase : Dashboard > SQL Editor > New query
-- puis cliquer sur "Run".
--
-- Pourquoi ? GitHub Pages ne peut pas exécuter de PHP : le site
-- communique alors DIRECTEMENT avec l'API REST de Supabase
-- (PostgREST) via la clé "anon" publique.
--
-- Ces politiques autorisent UNIQUEMENT :
--   - LECTURE publique : produits actifs, catégories, paramètres
--   - ÉCRITURE publique : nouvelles commandes, visites (compteur)
--
-- Le rôle "postgres" utilisé par le back-office PHP (Administration)
-- contourne automatiquement RLS, donc rien n'est bloqué côté admin.
-- =========================================================

-- ---------------------------------------------------------
-- 1) Activer Row Level Security sur chaque table publique
-- ---------------------------------------------------------
ALTER TABLE public.produits   ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.categories ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.parametres ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.commandes  ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.visiteurs  ENABLE ROW LEVEL SECURITY;

-- ---------------------------------------------------------
-- 2) LECTURE publique (le visiteur voit les données)
-- ---------------------------------------------------------
-- Produits : uniquement les produits actifs
DROP POLICY IF EXISTS "lecture_publique_produits" ON public.produits;
CREATE POLICY "lecture_publique_produits" ON public.produits
    FOR SELECT
    USING (actif = 1);

-- Catégories
DROP POLICY IF EXISTS "lecture_publique_categories" ON public.categories;
CREATE POLICY "lecture_publique_categories" ON public.categories
    FOR SELECT
    USING (true);

-- Paramètres de la boutique (téléphone, WhatsApp, ...)
DROP POLICY IF EXISTS "lecture_publique_parametres" ON public.parametres;
CREATE POLICY "lecture_publique_parametres" ON public.parametres
    FOR SELECT
    USING (true);

-- ---------------------------------------------------------
-- 3) ÉCRITURE publique (le visiteur peut laisser une commande)
-- ---------------------------------------------------------
-- Nouvelle commande (le client anonyme peut insérer)
DROP POLICY IF EXISTS "insertion_publique_commandes" ON public.commandes;
CREATE POLICY "insertion_publique_commandes" ON public.commandes
    FOR INSERT
    WITH CHECK (true);

-- Compteur de visites
DROP POLICY IF EXISTS "insertion_publique_visiteurs" ON public.visiteurs;
CREATE POLICY "insertion_publique_visiteurs" ON public.visiteurs
    FOR INSERT
    WITH CHECK (true);

-- =========================================================
-- FIN
-- =========================================================