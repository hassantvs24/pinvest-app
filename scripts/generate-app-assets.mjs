/**
 * Generates all Android icon + splash assets for the Capacitor app
 * directly from public/icon.svg using sharp (no @capacitor/assets needed,
 * its bundled sharp is broken on Node 24).
 *
 * Run: node scripts/generate-app-assets.mjs
 */
import sharp from 'sharp';
import { mkdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const svgPath = path.join(root, 'public', 'icon.svg');
const resDir = path.join(root, 'android', 'app', 'src', 'main', 'res');
const resourcesDir = path.join(root, 'resources');

const ICON_BG = '#33200f'; // matches the rounded rect in icon.svg
const SPLASH_BG = '#047857'; // emerald-700 — matches the app header

// Launcher icon densities: [folder, legacy size px, adaptive foreground px]
const DENSITIES = [
    ['mipmap-mdpi', 48, 108],
    ['mipmap-hdpi', 72, 162],
    ['mipmap-xhdpi', 96, 216],
    ['mipmap-xxhdpi', 144, 324],
    ['mipmap-xxxhdpi', 192, 432],
];

// Splash sizes used by the Capacitor Android template.
const SPLASHES = [
    ['drawable', 480, 320],
    ['drawable-port-mdpi', 320, 480],
    ['drawable-port-hdpi', 480, 800],
    ['drawable-port-xhdpi', 720, 1280],
    ['drawable-port-xxhdpi', 960, 1600],
    ['drawable-port-xxxhdpi', 1280, 1920],
    ['drawable-land-mdpi', 480, 320],
    ['drawable-land-hdpi', 800, 480],
    ['drawable-land-xhdpi', 1280, 720],
    ['drawable-land-xxhdpi', 1600, 960],
    ['drawable-land-xxxhdpi', 1920, 1280],
];

await mkdir(resourcesDir, { recursive: true });

async function renderIcon(size) {
    return sharp(svgPath).resize(size, size).png().toBuffer();
}

async function renderRounded(size) {
    // Circle-cropped icon for the round launcher variant.
    const icon = await renderIcon(size);
    const circle = Buffer.from(
        `<svg width="${size}" height="${size}"><circle cx="${size / 2}" cy="${size / 2}" r="${size / 2}" fill="#fff"/></svg>`,
    );
    return sharp(icon).composite([{ input: circle, blend: 'dest-in' }]).png().toBuffer();
}

// ---- Launcher icons (legacy square + round + adaptive foreground) ----
for (const [folder, size, fgSize] of DENSITIES) {
    const dir = path.join(resDir, folder);
    await mkdir(dir, { recursive: true });

    await sharp(await renderIcon(size * 4)) // supersample for crisp edges
        .resize(size, size)
        .png()
        .toFile(path.join(dir, 'ic_launcher.png'));

    await sharp(await renderRounded(size * 4))
        .resize(size, size)
        .png()
        .toFile(path.join(dir, 'ic_launcher_round.png'));

    // Adaptive foreground: icon centered, ~62% of the 108dp-safe canvas.
    const fgIcon = await renderIcon(Math.round(fgSize * 0.62));
    await sharp({
        create: { width: fgSize, height: fgSize, channels: 4, background: { r: 0, g: 0, b: 0, alpha: 0 } },
    })
        .composite([{ input: fgIcon, gravity: 'center' }])
        .png()
        .toFile(path.join(dir, 'ic_launcher_foreground.png'));
}

// ---- Adaptive icon background color (values/ic_launcher_background.xml) ----
// Kept in sync with ICON_BG; the foreground carries the art itself.

// ---- Splash screens: emerald background + centered icon ----
for (const [folder, w, h] of SPLASHES) {
    const dir = path.join(resDir, folder);
    await mkdir(dir, { recursive: true });

    const iconSize = Math.round(Math.min(w, h) * 0.45);
    const icon = await renderIcon(iconSize);
    await sharp({ create: { width: w, height: h, channels: 4, background: SPLASH_BG } })
        .composite([{ input: icon, gravity: 'center' }])
        .png()
        .toFile(path.join(dir, 'splash.png'));
}

// ---- Keep 1024px master copies in resources/ for future use ----
await renderIcon(1024).then((buf) => sharp(buf).toFile(path.join(resourcesDir, 'icon.png')));
await sharp({ create: { width: 2732, height: 2732, channels: 4, background: SPLASH_BG } })
    .composite([{ input: await renderIcon(Math.round(2732 * 0.4)), gravity: 'center' }])
    .png()
    .toFile(path.join(resourcesDir, 'splash.png'));

console.log('Android icons + splashes generated from public/icon.svg');
