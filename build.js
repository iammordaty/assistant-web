const esbuild = require('esbuild');
const chokidar = require('chokidar');
const fs = require('fs');
const path = require('path');

const IS_WATCH = process.argv.includes('--watch');
const IS_PRODUCTION = process.argv.includes('--production');

const BUILD_COOLDOWN_SECONDS = 15 * 60;

let lastDevBuildAt = 0;
let lastChangeAt = 0;

const nowSeconds = () => Math.floor(Date.now() / 1000);

const getCurrentDate = () => {
    const now = new Date();
    const nowTz = new Date(now.toLocaleString('en-US', { timeZone: 'Europe/Warsaw' }));

    return nowTz.toISOString();
};

const getFileSize = filePath => {
    try {
        const { size } = fs.statSync(filePath);
        return Math.round((size / 1024) * 100) / 100;
    } catch {
        return 0;
    }
};

const publicAliasPlugin = {
    name: 'public-alias',
    setup(build) {
        build.onResolve({ filter: /^@public\// }, args => ({
            path: path.resolve(__dirname, 'public', args.path.replace(/^@public\//, ''))
        }));
    }
};

const buildConfig = ({ buildTime = 0, fileSize = 0, mode = 'dev' }) => ({
    entryPoints: ['src/Assistant/Module/Mix/Resources/js/index.jsx'],
    outfile: 'public/js/mix.dist.js',
    bundle: true,
    jsx: 'automatic',
    platform: 'browser',
    minify: mode === 'prod',
    sourcemap: mode !== 'prod',
    plugins: [publicAliasPlugin],
    banner: {
        js: `
          window.assistant = {
            mix: {
              buildInfo: {
                buildDate: "${getCurrentDate()}",
                buildTimeMs: ${buildTime},
                buildSizeKb: ${fileSize},
                buildMode: "${mode}"
              }
            }
          };
        `
    }
});

const build = async mode => {
    const start = Date.now();

    await esbuild.build(buildConfig({ mode }));

    const buildTime = Date.now() - start;
    const fileSize = getFileSize('public/js/mix.dist.js');

    await esbuild.build(buildConfig({ buildTime, fileSize, mode }));

    return { buildTime, fileSize };
};

const resolveWatchMode = () => {
    const now = nowSeconds();

    const eligible =
        lastDevBuildAt > 0 &&
        now - lastDevBuildAt >= BUILD_COOLDOWN_SECONDS &&
        now - lastChangeAt >= BUILD_COOLDOWN_SECONDS;

    return eligible ? 'prod' : 'dev';
};

const startWatch = async () => {
    console.log('👀 Watch mode enabled');

    const initialMode = 'dev';
    const initial = await build(initialMode);
    lastDevBuildAt = nowSeconds();

    console.log(`✅ Initial build [${initialMode}] (${initial.buildTime}ms, ${initial.buildSizeKb}KB)`);

    const watchPaths = [
        'src/Assistant/Module/Mix/Resources/js',
        'public/js/modules'
    ];

    const watcher = chokidar.watch(watchPaths, {
        ignored: [
            'public/js/mix.dist.js',
            '**/@eaDir',
            '**/@eaDir/**',
            '**/*@SynoEAStream'
        ],
        ignoreInitial: true,
        persistent: true
    });

    watcher.on('ready', () => console.log('📁 Watching for changes…'));

    watcher.on('change', async changedPath => {
        lastChangeAt = nowSeconds();

        console.log(`🔧 Changed: ${changedPath}`);

        try {
            const mode = resolveWatchMode();
            const result = await build(mode);

            if (mode === 'dev') {
                lastDevBuildAt = nowSeconds();
            }

            console.log(`✅ Build [${mode}] (${result.buildTime}ms, ${result.fileSize}KB)`);
        } catch (err) {
            console.error('❌ Build error', err);
        }
    });
};

const main = async () => {
    try {
        if (IS_WATCH) {
            await startWatch();

            return;
        }

        const mode = IS_PRODUCTION ? 'prod' : 'dev';

        console.log(`🏗 Building [${mode}]...`);

        const result = await build(mode);

        console.log(`🎉 Build complete [${mode}] (${result.buildTime}ms, ${result.fileSize}KB)`);
    } catch (err) {
        console.error('🔥 Fatal build error', err);
        process.exit(1);
    }
};

main();
