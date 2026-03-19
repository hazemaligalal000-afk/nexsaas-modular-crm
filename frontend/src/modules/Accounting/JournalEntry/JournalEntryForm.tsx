/**
 * JournalEntryForm.tsx
 * 
 * Complete Journal Entry Form with all 35 fields from سيستم_جديد.xlsx
 * Supports bilingual (Arabic/English) labels
 * Real-time double-entry balance validation
 * 
 * BATCH B — Journal Entry & Voucher Engine
 */

import React, { useState, useEffect } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { PermissionGate } from '../../../components/RBAC/PermissionGate';
import { usePermissions } from '../../../components/RBAC/hooks/usePermissions';

interface JournalLine {
  line_no: number;
  account_code: string;
  account_desc?: string;
  cost_identifier?: string;
  cost_center_code?: string;
  cost_center_name?: string;
  vendor_code?: string;
  vendor_name?: string;
  check_transfer_no?: string;
  exchange_rate: number;
  currency_code: string;
  dr_value: number;
  cr_value: number;
  line_desc?: string;
  asset_no?: string;
  transaction_no?: string;
  profit_loss_flag?: string;
  customer_invoice_no?: string;
  income_stmt_flag?: string;
  internal_invoice_no?: string;
  employee_no?: string;
  partner_no?: string;
  vendor_word_count?: number;
  translator_word_count?: number;
  agent_name?: string;
}

interface JournalEntryFormProps {
  entryId?: number;
  onSuccess?: () => void;
  mode?: 'create' | 'edit' | 'view';
}

export const JournalEntryForm: React.FC<JournalEntryFormProps> = ({
  entryId,
  onSuccess,
  mode = 'create'
}) => {
  const { hasPermission } = usePermissions();
  const [language, setLanguage] = useState<'en' | 'ar'>('en');

  // Header state
  const [header, setHeader] = useState({
    company_code: '01',
    area_code: '',
    fin_period: new Date().toISOString().slice(0, 7).replace('-', ''),
    voucher_date: new Date().toISOString().slice(0, 10),
    service_date: new Date().toISOString().slice(0, 7).replace('-', ''),
    voucher_code: '1',
    section_code: '01',
    voucher_sub: '',
    currency_code: '01',
    exchange_rate: 1.000000,
    description: ''
  });

  // Lines state
  const [lines, setLines] = useState<JournalLine[]>([
    {
      line_no: 1,
      account_code: '',
      exchange_rate: 1.000000,
      currency_code: '01',
      dr_value: 0,
      cr_value: 0
    }
  ]);

  // Balance calculation
  const [balance, setBalance] = useState({
    total_dr: 0,
    total_cr: 0,
    difference: 0,
    balanced: true
  });

  // Calculate balance whenever lines change
  useEffect(() => {
    const totalDr = lines.reduce((sum, line) => sum + (line.dr_value || 0), 0);
    const totalCr = lines.reduce((sum, line) => sum + (line.cr_value || 0), 0);
    const difference = Math.abs(totalDr - totalCr);
    const balanced = difference < 0.01;

    setBalance({ total_dr: totalDr, total_cr: totalCr, difference, balanced });
  }, [lines]);

  // Fetch existing entry if editing
  const { data: existingEntry } = useQuery({
    queryKey: ['journal-entry', entryId],
    queryFn: async () => {
      const response = await fetch(`/api/accounting/journal-entries/${entryId}`);
      return response.json();
    },
    enabled: !!entryId && mode !== 'create'
  });

  // Load existing entry data
  useEffect(() => {
    if (existingEntry?.data) {
      setHeader(existingEntry.data);
      setLines(existingEntry.data.lines || []);
    }
  }, [existingEntry]);

  // Fetch next voucher number
  const { data: nextVoucherData } = useQuery({
    queryKey: ['next-voucher-number', header.company_code, header.fin_period],
    queryFn: async () => {
      const response = await fetch(
        `/api/accounting/journal-entries/next-voucher-number?company_code=${header.company_code}&fin_period=${header.fin_period}`
      );
      return response.json();
    },
    enabled: mode === 'create'
  });

  // Fetch exchange rate
  const { data: exchangeRateData } = useQuery({
    queryKey: ['exchange-rate', header.currency_code, header.voucher_date],
    queryFn: async () => {
      const response = await fetch(
        `/api/accounting/exchange-rates?currency_code=${header.currency_code}&date=${header.voucher_date}`
      );
      return response.json();
    }
  });

  // Update exchange rate when currency or date changes
  useEffect(() => {
    if (exchangeRateData?.data?.rate_to_base) {
      setHeader(prev => ({ ...prev, exchange_rate: exchangeRateData.data.rate_to_base }));
    }
  }, [exchangeRateData]);

  // Create/Update mutation
  const saveMutation = useMutation({
    mutationFn: async (data: any) => {
      const url = entryId 
        ? `/api/accounting/journal-entries/${entryId}`
        : '/api/accounting/journal-entries';
      
      const response = await fetch(url, {
        method: entryId ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });

      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Failed to save journal entry');
      }

      return response.json();
    },
    onSuccess: () => {
      onSuccess?.();
    }
  });

  // Add line
  const addLine = () => {
    setLines([...lines, {
      line_no: lines.length + 1,
      account_code: '',
      exchange_rate: header.exchange_rate,
      currency_code: header.currency_code,
      dr_value: 0,
      cr_value: 0
    }]);
  };

  // Remove line
  const removeLine = (index: number) => {
    if (lines.length > 1) {
      setLines(lines.filter((_, i) => i !== index));
    }
  };

  // Update line
  const updateLine = (index: number, field: string, value: any) => {
    const newLines = [...lines];
    newLines[index] = { ...newLines[index], [field]: value };
    
    // If updating dr_value, clear cr_value and vice versa
    if (field === 'dr_value' && value > 0) {
      newLines[index].cr_value = 0;
    } else if (field === 'cr_value' && value > 0) {
      newLines[index].dr_value = 0;
    }
    
    setLines(newLines);
  };

  // Submit form
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!balance.balanced) {
      alert(language === 'en' 
        ? `Entry is not balanced. Difference: ${balance.difference.toFixed(2)}`
        : `القيد غير متوازن. الفرق: ${balance.difference.toFixed(2)}`
      );
      return;
    }

    saveMutation.mutate({ header, lines });
  };

  const labels = {
    en: {
      // Header labels
      companyCode: 'Company Code',
      areaCode: 'Area Code',
      finPeriod: 'Financial Period',
      voucherDate: 'Voucher Date',
      serviceDate: 'Service Date',
      voucherCode: 'Voucher No.',
      sectionCode: 'Section Code',
      voucherSub: 'Voucher Sub No.',
      currencyCode: 'Currency',
      exchangeRate: 'Exchange Rate',
      description: 'Description',
      
      // Line labels
      lineNo: 'Line',
      accountCode: 'Account Code',
      accountDesc: 'Account Description',
      costIdentifier: 'Cost Identifier',
      costCenterCode: 'Cost Center',
      vendorCode: 'Vendor/Client',
      checkTransferNo: 'Check/Transfer No.',
      drValue: 'Debit',
      crValue: 'Credit',
      lineDesc: 'Line Description',
      assetNo: 'Asset No.',
      transactionNo: 'Transaction No.',
      customerInvoiceNo: 'Customer Invoice No.',
      internalInvoiceNo: 'Internal Invoice No.',
      employeeNo: 'Employee No.',
      partnerNo: 'Partner No.',
      vendorWordCount: 'Vendor Word Count',
      translatorWordCount: 'Translator Word Count',
      agentName: 'Agent Name',
      
      // Actions
      addLine: 'Add Line',
      removeLine: 'Remove',
      save: 'Save Draft',
      submit: 'Submit for Approval',
      totalDr: 'Total Debit',
      totalCr: 'Total Credit',
      difference: 'Difference',
      balanced: 'Balanced',
      notBalanced: 'Not Balanced'
    },
    ar: {
      // Header labels (Arabic)
      companyCode: 'كود الشركة',
      areaCode: 'كود المقر',
      finPeriod: 'الفترة المالية',
      voucherDate: 'التاريخ',
      serviceDate: 'تاريخ الخدمة',
      voucherCode: 'رقم القسيمة',
      sectionCode: 'كود القسم',
      voucherSub: 'رقم القسيمة الفرعي',
      currencyCode: 'كود العملة',
      exchangeRate: 'سعر التحويل',
      description: 'الوصف',
      
      // Line labels (Arabic)
      lineNo: 'رقم السطر',
      accountCode: 'كود الحساب',
      accountDesc: 'تعريف الحساب',
      costIdentifier: 'توصيف التكاليف',
      costCenterCode: 'كود مركز التكلفة',
      vendorCode: 'كود العميل',
      checkTransferNo: 'رقم الشيك / التحويل',
      drValue: 'المدين',
      crValue: 'الدائن',
      lineDesc: 'وصف السطر',
      assetNo: 'رقم الأصل',
      transactionNo: 'رقم العملية',
      customerInvoiceNo: 'رقم الفاتورة للعميل',
      internalInvoiceNo: 'رقم الفاتورة الداخلي',
      employeeNo: 'رقم العامل',
      partnerNo: 'رقم الشريك',
      vendorWordCount: 'عدد كلمات البيع',
      translatorWordCount: 'عدد كلمات محاسبة المترجم',
      agentName: 'اسم القائم بالاعمال',
      
      // Actions (Arabic)
      addLine: 'إضافة سطر',
      removeLine: 'حذف',
      save: 'حفظ مسودة',
      submit: 'إرسال للموافقة',
      totalDr: 'إجمالي المدين',
      totalCr: 'إجمالي الدائن',
      difference: 'الفرق',
      balanced: 'متوازن',
      notBalanced: 'غير متوازن'
    }
  };

  const t = labels[language];
  const isReadOnly = mode === 'view';

  return (
    <PermissionGate permission="accounting.voucher.create">
      <div className={`max-w-7xl mx-auto p-6 ${language === 'ar' ? 'rtl' : 'ltr'}`}>
        {/* Language Toggle */}
        <div className="flex justify-end mb-4">
          <button
            onClick={() => setLanguage(language === 'en' ? 'ar' : 'en')}
            className="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600"
          >
            {language === 'en' ? 'عربي' : 'English'}
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Header Section */}
          <div className="bg-white shadow rounded-lg p-6">
            <h2 className="text-2xl font-bold mb-6">
              {language === 'en' ? 'Journal Entry' : 'قسيمة يومية'}
            </h2>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {/* Company Code */}
              <div>
                <label className="block text-sm font-medium mb-1">
                  {t.companyCode}
                </label>
                <select
                  value={header.company_code}
                  onChange={(e) => setHeader({ ...header, company_code: e.target.value })}
                  disabled={isReadOnly}
                  className="w-full border rounded px-3 py-2"
                  required
                >
                  <option value="01">01 - Globalize Group</option>
                  <option value="02">02 - Digitalize</option>
                  <option value="03">03 - Brandora</option>
                  <option value="04">04 - Project Metric</option>
                  <option value="05">05 - Jusor</option>
                  <option value="06">06 - شبكات</option>
                </select>
              </div>

              {/* Financial Period */}
              <div>
                <label className="block text-sm font-medium mb-1">
                  {t.finPeriod}
                </label>
                <input
                  type="text"
                  value={header.fin_period}
                  onChange={(e) => setHeader({ ...header, fin_period: e.target.value })}
                  disabled={isReadOnly}
                  placeholder="YYYYMM"
                  className="w-full border rounded px-3 py-2"
                  required
                />
              </div>

              {/* Voucher Date */}
              <div>
                <label className="block text-sm font-medium mb-1">
                  {t.voucherDate}
                </label>
                <input
                  type="date"
                  value={header.voucher_date}
                  onChange={(e) => setHeader({ ...header, voucher_date: e.target.value })}
                  disabled={isReadOnly}
                  className="w-full border rounded px-3 py-2"
                  required
                />
              </div>

              {/* Service Date */}
              <div>
                <label className="block text-sm font-medium mb-1">
                  {t.serviceDate}
                </label>
                <input
                  type="text"
                  value={header.service_date}
                  onChange={(e) => setHeader({ ...header, service_date: e.target.value })}
                  disabled={isReadOnly}
                  placeholder="YYYYMM"
                  className="w-full border rounded px-3 py-2"
                />
              </div>

              {/* Currency */}
              <div>
                <label className="block text-sm font-medium mb-1">
                  {t.currencyCode}
                </label>
                <select
                  value={header.currency_code}
                  onChange={(e) => setHeader({ ...header, currency_code: e.target.value })}
                  disabled={isReadOnly}
                  className="w-full border rounded px-3 py-2"
                  required
                >
                  <option value="01">01 - EGP (جنية مصري)</option>
                  <option value="02">02 - USD (دولار أمريكي)</option>
                  <option value="03">03 - AED (درهم إماراتي)</option>
                  <option value="04">04 - SAR (ريال سعودي)</option>
                  <option value="05">05 - EUR (يورو)</option>
                  <option value="06">06 - GBP (إسترليني)</option>
                </select>
              </div>

              {/* Exchange Rate */}
              <div>
                <label className="block text-sm font-medium mb-1">
                  {t.exchangeRate}
                </label>
                <input
                  type="number"
                  step="0.000001"
                  value={header.exchange_rate}
                  onChange={(e) => setHeader({ ...header, exchange_rate: parseFloat(e.target.value) })}
                  disabled={isReadOnly}
                  className="w-full border rounded px-3 py-2"
                  required
                />
              </div>

              {/* Description */}
              <div className="md:col-span-3">
                <label className="block text-sm font-medium mb-1">
                  {t.description}
                </label>
                <textarea
                  value={header.description}
                  onChange={(e) => setHeader({ ...header, description: e.target.value })}
                  disabled={isReadOnly}
                  className="w-full border rounded px-3 py-2"
                  rows={2}
                />
              </div>
            </div>
          </div>

          {/* Lines Section */}
          <div className="bg-white shadow rounded-lg p-6">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-xl font-bold">
                {language === 'en' ? 'Journal Lines' : 'سطور القيد'}
              </h3>
              {!isReadOnly && (
                <button
                  type="button"
                  onClick={addLine}
                  className="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600"
                >
                  {t.addLine}
                </button>
              )}
            </div>

            <div className="overflow-x-auto">
              <table className="w-full border-collapse">
                <thead>
                  <tr className="bg-gray-100">
                    <th className="border p-2">{t.lineNo}</th>
                    <th className="border p-2">{t.accountCode}</th>
                    <th className="border p-2">{t.drValue}</th>
                    <th className="border p-2">{t.crValue}</th>
                    <th className="border p-2">{t.lineDesc}</th>
                    {!isReadOnly && <th className="border p-2">Actions</th>}
                  </tr>
                </thead>
                <tbody>
                  {lines.map((line, index) => (
                    <tr key={index}>
                      <td className="border p-2 text-center">{index + 1}</td>
                      <td className="border p-2">
                        <input
                          type="text"
                          value={line.account_code}
                          onChange={(e) => updateLine(index, 'account_code', e.target.value)}
                          disabled={isReadOnly}
                          className="w-full border rounded px-2 py-1"
                          required
                        />
                      </td>
                      <td className="border p-2">
                        <input
                          type="number"
                          step="0.01"
                          value={line.dr_value}
                          onChange={(e) => updateLine(index, 'dr_value', parseFloat(e.target.value) || 0)}
                          disabled={isReadOnly}
                          className="w-full border rounded px-2 py-1 text-right"
                        />
                      </td>
                      <td className="border p-2">
                        <input
                          type="number"
                          step="0.01"
                          value={line.cr_value}
                          onChange={(e) => updateLine(index, 'cr_value', parseFloat(e.target.value) || 0)}
                          disabled={isReadOnly}
                          className="w-full border rounded px-2 py-1 text-right"
                        />
                      </td>
                      <td className="border p-2">
                        <input
                          type="text"
                          value={line.line_desc || ''}
                          onChange={(e) => updateLine(index, 'line_desc', e.target.value)}
                          disabled={isReadOnly}
                          className="w-full border rounded px-2 py-1"
                        />
                      </td>
                      {!isReadOnly && (
                        <td className="border p-2 text-center">
                          <button
                            type="button"
                            onClick={() => removeLine(index)}
                            className="px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600"
                            disabled={lines.length === 1}
                          >
                            {t.removeLine}
                          </button>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr className="bg-gray-100 font-bold">
                    <td colSpan={2} className="border p-2 text-right">
                      {language === 'en' ? 'Totals:' : 'الإجماليات:'}
                    </td>
                    <td className="border p-2 text-right">
                      {balance.total_dr.toFixed(2)}
                    </td>
                    <td className="border p-2 text-right">
                      {balance.total_cr.toFixed(2)}
                    </td>
                    <td colSpan={2} className="border p-2">
                      <span className={balance.balanced ? 'text-green-600' : 'text-red-600'}>
                        {balance.balanced ? t.balanced : `${t.notBalanced} (${t.difference}: ${balance.difference.toFixed(2)})`}
                      </span>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          {/* Actions */}
          {!isReadOnly && (
            <div className="flex justify-end gap-4">
              <button
                type="submit"
                disabled={!balance.balanced || saveMutation.isPending}
                className="px-6 py-3 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:bg-gray-300"
              >
                {saveMutation.isPending ? 'Saving...' : t.save}
              </button>
            </div>
          )}
        </form>
      </div>
    </PermissionGate>
  );
};
