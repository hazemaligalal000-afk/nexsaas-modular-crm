/**
 * RTL Verification Script
 * Checks CSS files for proper logical properties usage
 * Requirements: 11
 */

import * as fs from 'fs';
import * as path from 'path';

interface RTLIssue {
  file: string;
  line: number;
  property: string;
  suggestion: string;
  severity: 'error' | 'warning';
}

// Physical properties that should be replaced with logical properties
const PHYSICAL_PROPERTIES: Record<string, string> = {
  'margin-left': 'margin-inline-start',
  'margin-right': 'margin-inline-end',
  'padding-left': 'padding-inline-start',
  'padding-right': 'padding-inline-end',
  'border-left': 'border-inline-start',
  'border-right': 'border-inline-end',
  'border-left-width': 'border-inline-start-width',
  'border-right-width': 'border-inline-end-width',
  'border-left-color': 'border-inline-start-color',
  'border-right-color': 'border-inline-end-color',
  'border-left-style': 'border-inline-start-style',
  'border-right-style': 'border-inline-end-style',
  'left': 'inset-inline-start',
  'right': 'inset-inline-end',
  'text-align: left': 'text-align: start',
  'text-align: right': 'text-align: end',
  'float: left': 'float: inline-start',
  'float: right': 'float: inline-end',
  'clear: left': 'clear: inline-start',
  'clear: right': 'clear: inline-end',
};

// Exceptions: properties that are allowed to use physical directions
const ALLOWED_EXCEPTIONS = [
  'border-radius', // Border radius doesn't need logical properties
  'box-shadow', // Box shadow is fine with physical properties
  'transform', // Transform is handled separately
  'background-position', // Background position is fine
];

const issues: RTLIssue[] = [];

/**
 * Check if we're inside a [dir="rtl"] or [dir="ltr"] block
 */
function isInDirectionBlock(lines: string[], currentLine: number): boolean {
  // Look backwards to find if we're in a [dir="rtl"] or [dir="ltr"] block
  for (let i = currentLine - 1; i >= 0; i--) {
    const line = lines[i].trim();
    
    // Found a direction-specific selector
    if (line.includes('[dir="rtl"]') || line.includes('[dir="ltr"]')) {
      // Check if we've closed the block
      let braceCount = 0;
      for (let j = i; j < currentLine; j++) {
        const checkLine = lines[j];
        braceCount += (checkLine.match(/{/g) || []).length;
        braceCount -= (checkLine.match(/}/g) || []).length;
      }
      // If braceCount > 0, we're still inside the block
      return braceCount > 0;
    }
    
    // If we hit a closing brace at the same level, we're not in a direction block
    if (line === '}') {
      return false;
    }
  }
  
  return false;
}

/**
 * Check if a line contains a physical property that should be logical
 */
function checkLine(line: string, lineNumber: number, filePath: string, allLines: string[]): void {
  const trimmedLine = line.trim();
  
  // Skip comments
  if (trimmedLine.startsWith('//') || trimmedLine.startsWith('/*') || trimmedLine.startsWith('*')) {
    return;
  }

  // Skip lines with exceptions
  for (const exception of ALLOWED_EXCEPTIONS) {
    if (trimmedLine.includes(exception)) {
      return;
    }
  }

  // Skip if we're in a [dir="rtl"] or [dir="ltr"] block
  if (isInDirectionBlock(allLines, lineNumber - 1)) {
    return;
  }

  // Check for physical properties
  for (const [physical, logical] of Object.entries(PHYSICAL_PROPERTIES)) {
    // Skip if this is just in a comment explaining the mapping
    if (trimmedLine.includes('→') || trimmedLine.includes('instead of')) {
      continue;
    }

    if (trimmedLine.includes(physical)) {
      issues.push({
        file: filePath,
        line: lineNumber,
        property: physical,
        suggestion: logical,
        severity: 'error',
      });
    }
  }

  // Check for text-align: left/right (but not in comments)
  if (trimmedLine.match(/text-align:\s*(left|right)/) && !trimmedLine.includes('/*') && !trimmedLine.includes('//')) {
    const match = trimmedLine.match(/text-align:\s*(left|right)/);
    if (match) {
      const direction = match[1];
      const logical = direction === 'left' ? 'start' : 'end';
      
      issues.push({
        file: filePath,
        line: lineNumber,
        property: `text-align: ${direction}`,
        suggestion: `text-align: ${logical}`,
        severity: 'error',
      });
    }
  }
}

/**
 * Recursively scan directory for CSS files
 */
function scanDirectory(dir: string): string[] {
  const files: string[] = [];
  
  try {
    const entries = fs.readdirSync(dir, { withFileTypes: true });
    
    for (const entry of entries) {
      const fullPath = path.join(dir, entry.name);
      
      if (entry.isDirectory()) {
        // Skip node_modules and other build directories
        if (!['node_modules', 'dist', 'build', '.git'].includes(entry.name)) {
          files.push(...scanDirectory(fullPath));
        }
      } else if (entry.isFile() && (entry.name.endsWith('.css') || entry.name.endsWith('.scss'))) {
        files.push(fullPath);
      }
    }
  } catch (error) {
    console.error(`Error scanning directory ${dir}:`, error);
  }
  
  return files;
}

/**
 * Check a CSS file for RTL issues
 */
function checkFile(filePath: string): void {
  try {
    const content = fs.readFileSync(filePath, 'utf-8');
    const lines = content.split('\n');
    
    lines.forEach((line, index) => {
      checkLine(line, index + 1, filePath, lines);
    });
  } catch (error) {
    console.error(`Error reading file ${filePath}:`, error);
  }
}

/**
 * Main verification function
 */
function verifyRTL(): void {
  console.log('🔍 Verifying RTL implementation...\n');
  
  const srcDir = path.join(__dirname, '..', 'src');
  const cssFiles = scanDirectory(srcDir);
  
  console.log(`Found ${cssFiles.length} CSS files to check\n`);
  
  // Check each file
  cssFiles.forEach(checkFile);
  
  // Report results
  if (issues.length === 0) {
    console.log('✅ No RTL issues found! All CSS files use logical properties correctly.\n');
    process.exit(0);
  } else {
    console.log(`❌ Found ${issues.length} RTL issues:\n`);
    
    // Group issues by file
    const issuesByFile: Record<string, RTLIssue[]> = {};
    issues.forEach(issue => {
      if (!issuesByFile[issue.file]) {
        issuesByFile[issue.file] = [];
      }
      issuesByFile[issue.file].push(issue);
    });
    
    // Print issues grouped by file
    Object.entries(issuesByFile).forEach(([file, fileIssues]) => {
      const relativePath = path.relative(process.cwd(), file);
      console.log(`\n📄 ${relativePath}`);
      console.log('─'.repeat(80));
      
      fileIssues.forEach(issue => {
        const icon = issue.severity === 'error' ? '❌' : '⚠️';
        console.log(`  ${icon} Line ${issue.line}: ${issue.property}`);
        console.log(`     Suggestion: Use "${issue.suggestion}" instead`);
      });
    });
    
    console.log('\n');
    console.log('💡 Tips:');
    console.log('  - Replace physical properties (left/right) with logical properties (inline-start/inline-end)');
    console.log('  - Use text-align: start/end instead of left/right');
    console.log('  - Physical properties are only allowed in [dir="rtl"] or [dir="ltr"] specific blocks');
    console.log('\n');
    
    process.exit(1);
  }
}

// Run verification
verifyRTL();
