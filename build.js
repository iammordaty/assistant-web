const esbuild = require('esbuild');
const chokidar = require('chokidar');
const fs = require('fs');
const path = require('path');

const isWatch = process.argv.includes('--watch');
const isProduction = process.argv.includes('--production');

function getCurrentBuildDate() {
  const now = new Date();
  const polandTime = new Date(now.toLocaleString("en-US", {timeZone: "Europe/Warsaw"}));
  
  return polandTime.toISOString();
}

function getFileSizeInKB(filePath) {
  try {
    const stats = fs.statSync(filePath);
    return Math.round(stats.size / 1024 * 100) / 100;
  } catch (error) {
    return 0;
  }
}

const publicAliasPlugin = {
  name: 'public-alias',
  setup(build) {
    build.onResolve({ filter: /^@public\// }, args => {
      const modulePath = args.path.replace(/^@public\//, '');
      return {
        path: path.resolve(__dirname, 'public', modulePath)
      };
    });
  }
};

function getBuildOptions(buildTime = 0, fileSize = 0) {
  return {
    entryPoints: ['src/Assistant/Module/Mix/Resources/js/index.jsx'],
    bundle: true,
    outfile: 'public/js/mix.dist.js',
    jsx: 'automatic',
    platform: 'browser',
    minify: isProduction,
    sourcemap: !isProduction,
    plugins: [publicAliasPlugin],
    banner: {
      js: `window.assistant = { mix: { buildInfo: { buildDate: "${getCurrentBuildDate()}", buildTimeMs: ${buildTime}, buildSizeKb: ${fileSize} } } };`
    }
  };
}

async function performBuild() {
  const startTime = Date.now();
  
  await esbuild.build(getBuildOptions());
  
  const buildTime = Date.now() - startTime;
  const fileSize = getFileSizeInKB('public/js/mix.dist.js');
  
  await esbuild.build(getBuildOptions(buildTime, fileSize));
  
  return { buildTime, fileSize };
}

async function build() {
  try {
    if (isWatch) {
      console.log('Starting watch mode...');
      
      const { buildTime, fileSize } = await performBuild();
      console.log(`✅ Initial build complete (${buildTime}ms, ${fileSize}KB)`);
      
      console.log('Watching for changes...');
      
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
        persistent: true,
        ignoreInitial: true
      });
      
      watcher.on('ready', () => {
        console.log('👀 File watcher is ready');
        console.log('📁 Watching paths:', watcher.getWatched());
      });
      
      watcher.on('change', async (path) => {
        console.log(`🔨 File changed (${path}), rebuilding...`);
        try {
          const { buildTime, fileSize } = await performBuild();
          console.log(`✅ Build successful (${buildTime}ms, ${fileSize}KB)`);
        } catch (error) {
          console.error('❌ Build failed:', error);
        }
      });
    } else {
      console.log('Building...');
      const { buildTime, fileSize } = await performBuild();
      console.log(`Build complete! (${buildTime}ms, ${fileSize}KB)`);
    }
  } catch (error) {
    console.error('Build failed:', error);
    process.exit(1);
  }
}

build(); 
