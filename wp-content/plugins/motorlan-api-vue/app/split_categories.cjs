const fs = require('fs');
const path = require('path');

// Update filename if necessary
const inputFilePath = path.join(__dirname, 'Categorias-Export-2026-February-08-0029.csv');

let content;
try {
	content = fs.readFileSync(inputFilePath, 'utf8');
} catch (error) {
	console.error(`Error reading file ${inputFilePath}:`, error.message);
	// Create dummy content or exit gracefully if optional, but here we prefer to exit/log
	// Proceeding with empty content won't execute logs.
	content = "";
}

// Strip BOM if present
if (content.length > 0 && content.charCodeAt(0) === 0xFEFF) {
	content = content.slice(1);
}

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

// Convert row array back to CSV string
function rowToCSV(row) {
	return row.map(field => {
		// Escape quotes
		const escaped = field.replace(/"/g, '""');
		// Wrap in quotes if contains special chars or is standard
		if (/[",\n\r]/.test(field) || true) {
			return `"${escaped}"`;
		}
		return escaped;
	}).join(',');
}

console.log('Parsing CSV content...');
const allRows = parseCSV(content);
console.log(`Parsed ${allRows.length} rows.`);

if (allRows.length === 0) {
	console.log('No rows found (or file missing).');
	process.exit(0);
}

const headerRow = allRows[0];
const headerLine = rowToCSV(headerRow);
const dataRows = allRows.slice(1);

const languages = {
	'es': [],
	'en': [],
	'eu': []
};

function getLanguageFromUrl(url) {
	if (!url) return null;
	if (url.includes('/es/')) return 'es';
	if (url.includes('/en/')) return 'en';
	if (url.includes('/eu/')) return 'eu';
	return null;
}

// Determine permalink index - simplistic assumption it's at index 1 as per original script
// Or verify headers
const permalinkIndex = 1;

dataRows.forEach(row => {
	if (row.length <= permalinkIndex) return;

	const permalink = row[permalinkIndex];
	if (permalink) {
		const lang = getLanguageFromUrl(permalink);
		if (lang && languages[lang]) {
			languages[lang].push(row);
		}
	}
});

Object.keys(languages).forEach(lang => {
	const outputFileName = `Categorias-${lang.toUpperCase()}.csv`;
	const outputPath = path.join(__dirname, outputFileName);

	if (languages[lang].length > 0) {
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
