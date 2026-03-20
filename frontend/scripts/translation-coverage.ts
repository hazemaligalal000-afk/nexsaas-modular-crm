/**
 * Translation Coverage Report Script
 * Checks translation completeness and reports missing keys
 * Requirements: 10, 26
 */

import * as fs from 'fs';
import * as path from 'path';

interface TranslationObject {
  [key: string]: string | TranslationObject;
}

function flattenObject(obj: TranslationObject, prefix = ''): Record<string, string> {
  return Object.keys(obj).reduce((acc, key) => {
    const value = obj[key];
    const newKey = prefix ? `${prefix}.${key}` : key;
    
    if (typeof value === 'object' && value !== null) {
      Object.assign(acc, flattenObject(value as TranslationObject, newKey));
    } else {
      acc[newKey] = value as string;
    }
    
    return acc;
  }, {} as Record<string, string>);
}

function loadTranslationFile(locale: string): TranslationObject {
  const filePath = path.join(__dirname, '..', 'src', 'i18n', 'locales', `${locale}.json`);
  const content = fs.readFileSync(filePath, 'utf-8');
  return JSON.parse(content);
}

function calculateCoverage() {
  console.log('Translation Coverage Report');
  console.log('===========================\n');

  try {
    const enUS = loadTranslationFile('en-US');
    const arSA = loadTranslationFile('ar-SA');
    
    const enFlat = flattenObject(enUS);
    const arFlat = flattenObject(arSA);
    
    const totalKeys = Object.keys(enFlat).length;
    const translatedKeys = Object.keys(arFlat).length;
    const missingKeys = Object.keys(enFlat).filter(key => !arFlat[key]);
    const extraKeys = Object.keys(arFlat).filter(key => !enFlat[key]);
    
    const coverage = (translatedKeys / totalKeys) * 100;
    
    console.log(`Base Language (en-US):`);
    console.log(`  Total keys: ${totalKeys}\n`);
    
    console.log(`Target Language (ar-SA):`);
    console.log(`  Translated keys: ${translatedKeys}`);
    console.log(`  Coverage: ${coverage.toFixed(2)}%\n`);
    
    if (missingKeys.length > 0) {
      console.log(`Missing translations in ar-SA (${missingKeys.length}):`);
      missingKeys.forEach(key => {
        console.log(`  ❌ ${key}`);
        console.log(`     en-US: "${enFlat[key]}"`);
      });
      console.log('');
    } else {
      console.log('✅ All keys are translated!\n');
    }
    
    if (extraKeys.length > 0) {
      console.log(`Extra keys in ar-SA not in en-US (${extraKeys.length}):`);
      extraKeys.forEach(key => {
        console.log(`  ⚠️  ${key}`);
      });
      console.log('');
    }
    
    // Summary
    console.log('Summary:');
    console.log('--------');
    console.log(`Total keys in base: ${totalKeys}`);
    console.log(`Translated: ${translatedKeys - extraKeys.length}`);
    console.log(`Missing: ${missingKeys.length}`);
    console.log(`Extra: ${extraKeys.length}`);
    console.log(`Coverage: ${coverage.toFixed(2)}%\n`);
    
    if (coverage >= 100 && extraKeys.length === 0) {
      console.log('✅ Translation coverage is complete!');
      return true;
    } else {
      console.log('❌ Translation coverage is incomplete.');
      return false;
    }
  } catch (error) {
    console.error('Error reading translation files:', error);
    return false;
  }
}

const isComplete = calculateCoverage();
process.exit(isComplete ? 0 : 1);
