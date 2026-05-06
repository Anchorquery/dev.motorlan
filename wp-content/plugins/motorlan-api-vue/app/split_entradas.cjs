const fs = require('fs');
const path = require('path');

const inputFilePath = path.join(__dirname, 'Entradas-Export-2026-February-07-2222.csv');
let content = fs.readFileSync(inputFilePath, 'utf8');

// Strip BOM if present
if (content.charCodeAt(0) === 0xFEFF) {
	content = content.slice(1);
}

const languages = {
	'es': [],
	'en': [],
	'eu': []
};

// Robust CSV parser handling multiline fields
function parseCSV(text) {
	const rows = [];
	let currentRow = [];
	let currentField = '';
	let inQuotes = false;

	for (let i = 0; i < text.length; i++) {
		const char = text[i];
		const nextChar = text[i + 1];

		if (char === '"') {
			if (inQuotes && nextChar === '"') {
				// Escaped quote: "" -> "
				currentField += '"';
				i++; // Skip next quote
			} else {
				// Toggle quote state
				inQuotes = !inQuotes;
			}
		} else if (char === ',' && !inQuotes) {
			// End of field
			currentRow.push(currentField);
			currentField = '';
		} else if ((char === '\r' || char === '\n') && !inQuotes) {
			// End of row
			if (char === '\r' && nextChar === '\n') {
				i++; // Handle CRLF
			}
			currentRow.push(currentField);
			rows.push(currentRow);
			currentRow = [];
			currentField = '';
		} else {
			currentField += char;
		}
	}

	// Add last row if not empty
	if (currentField || currentRow.length > 0) {
		currentRow.push(currentField);
		rows.push(currentRow);
	}

	return rows;
}

console.log('Parsing CSV content...');
const allRows = parseCSV(content);
console.log(`Parsed ${allRows.length} rows.`);

if (allRows.length === 0) {
	console.log('No rows found.');
	process.exit(1);
}

const headerRow = allRows[0];
// Convert header row array back to CSV string line properly
function rowToCSV(row) {
	return row.map(field => {
		// Escape quotes
		const escaped = field.replace(/"/g, '""');
		// Wrap in quotes if contains special chars or is standard
		if (/[",\n\r]/.test(field) || true) { // Always quote for safety/consistency with original if feasible, or just special chars
			return `"${escaped}"`;
		}
		return escaped;
	}).join(',');
}

const headerLine = rowToCSV(headerRow);
const dataRows = allRows.slice(1);

// Identify index of "WPML Language Code"
// Based on file view: ID,Title,...,"WPML Language Code"
// Header row values likely match.
const langIndex = headerRow.findIndex(h => h.includes('WPML Language Code') || h.trim() === 'WPML Language Code');

console.log(`WPML Language Code index: ${langIndex}`);

if (langIndex === -1) {
	console.log('Could not find "WPML Language Code" column in header');
	console.log('Header columns:', headerRow);
	// Based on previous attempt, it was index 8
	// Let's fallback to 8 if not found, but log it
}

const targetIndex = langIndex !== -1 ? langIndex : 8;

dataRows.forEach(row => {
	if (row.length <= targetIndex) return;

	const langCode = row[targetIndex];
	if (langCode) {
		const cleanLang = langCode.trim(); // Parser handles quotes removal already

		if (languages[cleanLang]) {
			languages[cleanLang].push(row);
		} else if (cleanLang === 'es' || cleanLang === 'en' || cleanLang === 'eu') {
			languages[cleanLang].push(row);
		}
	}
});

console.log('Language counts:', {
	es: languages.es.length,
	en: languages.en.length,
	eu: languages.eu.length
});

Object.keys(languages).forEach(lang => {
	const outputFileName = `Entradas-${lang.toUpperCase()}.csv`;
	const outputPath = path.join(__dirname, outputFileName);

	if (languages[lang].length > 0) {
		// Reconstruct CSV lines
		const lines = languages[lang].map(row => rowToCSV(row));
		const outputContent = [headerLine, ...lines].join('\n');
		// Write with BOM for Excel compatibility
		const bom = '\ufeff';
		fs.writeFileSync(outputPath, bom + outputContent, 'utf8');
		console.log(`Created: ${outputFileName} with ${languages[lang].length} entries.`);
	} else {
		console.log(`No entries found for language: ${lang}`);
	}
});
