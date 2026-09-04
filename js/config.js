/**
 * =========================================================
 * KHANI Fashion - Configuration publique (GitHub Pages)
 * =========================================================
 *
 * Ce fichier est PUBLIC (il est envoyé au navigateur).
 * Il ne contient AUCUN secret : la clé "anon" de Supabase est
 * conçue pour être utilisée depuis un navigateur.
 *
 * Comment récupérer ces valeurs ?
 *   Supabase Dashboard > projet kvhajluxwvznhudiwfmg
 *   > Settings (engrenage) > API Keys
 *     - Project URL      -> supabaseUrl
 *     - anon public key  -> supabaseAnonKey
 */
const KHANI_CONFIG = {
    /** URL du projet Supabase */
    supabaseUrl: "https://kvhajluxwvznhudiwfmg.supabase.co",

    /**
     * Clé "anon" publique.
     * Laissez vide tant que vous ne l'avez pas copiée :
     * la boutique fonctionnera quand même avec le mode
     * de secours local (js/fallback.js).
     */
    supabaseAnonKey: "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imt2aGFqbHV4d3Z6bmh1ZGl3Zm1nIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODg1MjQ5MTcsImV4cCI6MjEwNDEwMDkxN30.FtK42jQ630qMM-vUMD7qj1rWDbh4oIAW212iHX2AumU"
};