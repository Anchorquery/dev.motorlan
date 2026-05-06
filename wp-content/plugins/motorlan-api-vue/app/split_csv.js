const fs = require('fs');
const path = require('path');

const inputFilePath = path.join(__dirname, 'Etiquetas-Export-2026-February-08-0032.csv');
const content = fs.readFileSync(inputFilePath, 'utf8');

const lines = content.split('\n');
const header = lines[0];
const dataLines = lines.slice(1).filter(line => line.trim() !== '');

const languages = {
	'es': [],
	'en': [],
	'eu': []
};

function getLanguageFromUrl(url) {
	if (url.includes('/es/')) return 'es';
	if (url.includes('/en/')) return 'en';
	if (url.includes('/eu/')) return 'eu';
	return null;
}

dataLines.forEach(line => {
	// Very basic CSV split (handling quotes)
	// Since we only need the 2nd column (index 1) which is Term Permalink
	const parts = [];
	let current = '';
	let inQuotes = false;

	for (let i = 0; i < line.length; i++) {
		const char = line[i];
		if (char === '"') {
			inQuotes = !inQuotes;
		} else if (char === ',' && !inQuotes) {
			parts.push(current);
			current = '';
		} else {
			current += char;
		}
	}
	parts.push(current);

	const permalink = parts[1];
	if (permalink) {
		const lang = getLanguageFromUrl(permalink);
		if (lang && languages[lang]) {
			languages[lang].push(line);
		}
	}
});

Object.keys(languages).forEach(lang => {
	const outputFileName = `Etiquetas-${lang.toUpperCase()}.csv`;
	const outputPath = path.join(__dirname, outputFileName);
	const outputContent = [header, ...languages[lang]].join('\n');
	fs.writeFileSync(outputPath, outputContent, 'utf8');
	console.log(`Created: ${outputFileName}`);
});

fs.writeFileSync(path.join(__dirname, 'done.txt'), 'done', 'utf8');
