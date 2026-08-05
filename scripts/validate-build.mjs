import {
  existsSync,
  readdirSync,
  readFileSync,
  statSync,
} from 'node:fs';
import {
  extname,
  join,
  resolve,
} from 'node:path';

const distPath =
  resolve('dist');

const indexPath =
  join(
    distPath,
    'index.html'
  );

if (!existsSync(indexPath)) {
  throw new Error(
    'dist/index.html não foi gerado'
  );
}

const textExtensions =
  new Set([
    '.html',
    '.js',
    '.css',
    '.json',
    '.map',
  ]);

const forbidden = [
  {
    value:
      'http://localhost:3101',
    message:
      'URL local do backend encontrada no bundle de produção',
  },
  {
    value:
      'api.openweathermap.org',
    message:
      'Chamada direta à OpenWeather encontrada no frontend',
  },
  {
    value:
      'VITE_OPENWEATHER_API_KEY',
    message:
      'Referência à antiga chave VITE encontrada no bundle',
  },
];

const files = [];

function walk(directory) {
  for (
    const entry
    of readdirSync(directory)
  ) {
    const path =
      join(
        directory,
        entry
      );

    const stat =
      statSync(path);

    if (stat.isDirectory()) {
      walk(path);
      continue;
    }

    files.push(path);
  }
}

walk(distPath);

const assetFiles =
  files.filter(
    (path) =>
      path.includes(
        `${join('dist', 'assets')}`
      )
  );

if (assetFiles.length === 0) {
  throw new Error(
    'Nenhum asset foi gerado em dist/assets'
  );
}

for (
  const path
  of files
) {
  if (
    !textExtensions.has(
      extname(path)
    )
  ) {
    continue;
  }

  const content =
    readFileSync(
      path,
      'utf-8'
    );

  for (
    const rule
    of forbidden
  ) {
    if (
      content.includes(
        rule.value
      )
    ) {
      throw new Error(
        `${rule.message}: ${path}`
      );
    }
  }
}

console.log(
  `✅ Build validado: ${files.length} arquivos`
);
console.log(
  '✅ Produção usa API na mesma origem'
);
console.log(
  '✅ Nenhuma chave OpenWeather no bundle'
);
