/*
 * Fond animé : des braises dorées qui montent en ondulant devant le village.
 * Un seul canvas, des sprites pré-dessinés (pas de flou coûteux à chaque image), arrêt automatique
 * quand l'onglet est caché, aucune animation si l'utilisateur demande moins de mouvement.
 */
(() => {
    const canvas = document.getElementById('braises');
    if (!canvas || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    let largeur = 0;
    let hauteur = 0;
    let ratio = 1;

    // Un sprite de braise : un point lumineux avec un halo, dessiné une fois puis réutilisé.
    const sprite = (couleurs) => {
        const taille = 64;
        const s = document.createElement('canvas');
        s.width = s.height = taille;
        const c = s.getContext('2d');
        const g = c.createRadialGradient(taille / 2, taille / 2, 0, taille / 2, taille / 2, taille / 2);
        couleurs.forEach(([stop, couleur]) => g.addColorStop(stop, couleur));
        c.fillStyle = g;
        c.fillRect(0, 0, taille, taille);
        return s;
    };
    const sprites = [
        sprite([[0, 'rgba(255,248,200,1)'], [0.18, 'rgba(255,196,80,.95)'], [0.5, 'rgba(255,120,30,.35)'], [1, 'rgba(255,90,10,0)']]),
        sprite([[0, 'rgba(255,255,230,1)'], [0.2, 'rgba(255,224,120,.9)'], [0.55, 'rgba(255,170,50,.3)'], [1, 'rgba(255,140,20,0)']]),
        sprite([[0, 'rgba(240,255,200,1)'], [0.2, 'rgba(160,235,90,.85)'], [0.55, 'rgba(90,190,40,.28)'], [1, 'rgba(60,160,20,0)']]), // élixir
    ];

    let particules = [];
    const hasard = (min, max) => min + Math.random() * (max - min);

    const creer = (departBas) => ({
        x: hasard(0, largeur),
        y: departBas ? hauteur + hasard(10, 60) : hasard(0, hauteur),
        taille: hasard(10, 34),
        vitesse: hasard(14, 46),
        amplitude: hasard(14, 56),
        frequence: hasard(0.25, 0.8),
        phase: hasard(0, Math.PI * 2),
        clignote: hasard(0.8, 2.4),
        sprite: sprites[Math.random() < 0.12 ? 2 : Math.random() < 0.5 ? 0 : 1],
        base: hasard(0.35, 0.95),
    });

    const ajuster = () => {
        ratio = Math.min(devicePixelRatio || 1, 1.5);
        largeur = innerWidth;
        hauteur = innerHeight;
        canvas.width = Math.round(largeur * ratio);
        canvas.height = Math.round(hauteur * ratio);
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        const nombre = Math.max(16, Math.min(46, Math.round(largeur / 34)));
        particules = Array.from({length: nombre}, () => creer(false));
    };
    ajuster();
    addEventListener('resize', ajuster);

    let precedent = performance.now();
    let actif = true;
    const image = (maintenant) => {
        if (!actif) return;
        const dt = Math.min(0.05, (maintenant - precedent) / 1000);
        precedent = maintenant;
        const t = maintenant / 1000;
        ctx.clearRect(0, 0, largeur, hauteur);
        for (let i = 0; i < particules.length; i++) {
            const p = particules[i];
            p.y -= p.vitesse * dt;
            const x = p.x + Math.sin(t * p.frequence * 2 + p.phase) * p.amplitude;
            // La braise s'éteint en montant, et clignote un peu.
            const vie = Math.max(0, Math.min(1, p.y / hauteur));
            const alpha = p.base * (0.45 + 0.55 * Math.sin(t * p.clignote + p.phase) ** 2) * (0.25 + 0.75 * vie);
            ctx.globalAlpha = alpha;
            ctx.drawImage(p.sprite, x - p.taille / 2, p.y - p.taille / 2, p.taille, p.taille);
            if (p.y < -40) particules[i] = creer(true);
        }
        ctx.globalAlpha = 1;
        requestAnimationFrame(image);
    };
    requestAnimationFrame(image);

    // Pas de travail inutile quand l'onglet est caché.
    document.addEventListener('visibilitychange', () => {
        actif = !document.hidden;
        if (actif) { precedent = performance.now(); requestAnimationFrame(image); }
    });
})();
