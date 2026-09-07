const EHeartExport = {
  COLORS: {
    navy: '1F3864',
    green: '1E7E34',
    greenBg: 'E6F4EA',
    pink: 'DB2777',
    pinkBg: 'FCE7F3',
    gray: '64748B',
    grayBg: 'F1F5F9',
    headerBg: '1F3864',
    headerText: 'FFFFFF',
    statBg: 'F8FAFC',
    border: 'D9D9D9',
    active: { fg: '1E7E34', bg: 'E6F4EA' },
    pending: { fg: 'B8860B', bg: 'FDF1D6' },
    redeemed: { fg: '4338CA', bg: 'E0E7FF' },
    for_redemption: { fg: 'DB2777', bg: 'FCE7F3' },
    rejected: { fg: 'C0392B', bg: 'FBE4E4' },
    cancelled: { fg: 'C0392B', bg: 'FBE4E4' },
    expired: { fg: 'C0392B', bg: 'FBE4E4' },
    for_revision: { fg: 'B8860B', bg: 'FDF1D6' }
  },

  _thinBorder(color) {
    const side = { style: 'thin', color: { rgb: color } };
    return { top: side, bottom: side, left: side, right: side };
  },

  /**
   * Build and download a styled .xlsx file.
   */
  toExcel(opts) {
    if (typeof XLSX === 'undefined') {
      console.error('EHeartExport: XLSX library not loaded.');
      if (window.EHeart?.toast) EHeart.toast('Export library not loaded.', 'error');
      return;
    }

    const {
      fileName = 'export',
      sheetName = 'Sheet1',
      title = '',
      subtitle = '',
      stats = [],
      columns = [],
      rows = [],
      colWidth = 20,
      statusColumn = null
    } = opts;

    if (!rows.length) {
      if (window.EHeart?.toast) EHeart.toast('No data to export.', 'error');
      return;
    }

    const C = this.COLORS;
    const lastCol = Math.max(columns.length - 1, 3);
    const sheetData = [];
    const merges = [];
    const cellStyles = {}; // "R_C" -> style object
    let r = 0;

    const setStyle = (row, col, style) => { cellStyles[`${row}_${col}`] = style; };

    // TITLE
    if (title) {
      sheetData.push([title]);
      merges.push({ s: { r, c: 0 }, e: { r, c: lastCol } });
      setStyle(r, 0, {
        font: { bold: true, sz: 18, color: { rgb: C.navy } },
        alignment: { horizontal: 'left', vertical: 'center' }
      });
      r++;
    }

    // SUBTITLE
    if (subtitle) {
      sheetData.push([subtitle]);
      merges.push({ s: { r, c: 0 }, e: { r, c: lastCol } });
      setStyle(r, 0, {
        font: { italic: true, sz: 11, color: { rgb: C.gray } }
      });
      r++;
    }

    if (title || subtitle) {
      sheetData.push([]);
      r++;
    }

    // STATS ROW (each stat gets its own little colored "card": label row + value row)
    if (stats.length) {
      const labelRow = [];
      const valueRow = [];
      stats.forEach(([label, value]) => {
        labelRow.push(label, '', '');
        valueRow.push(value, '', '');
      });
      sheetData.push(labelRow);
      const labelRowIdx = r;
      sheetData.push(valueRow);
      const valueRowIdx = r + 1;
      sheetData.push([]);

      stats.forEach((_, i) => {
        const startCol = i * 3;
        const endCol = startCol + 1;
        merges.push({ s: { r: labelRowIdx, c: startCol }, e: { r: labelRowIdx, c: endCol } });
        merges.push({ s: { r: valueRowIdx, c: startCol }, e: { r: valueRowIdx, c: endCol } });

        setStyle(labelRowIdx, startCol, {
          font: { bold: true, sz: 10, color: { rgb: C.gray } },
          fill: { fgColor: { rgb: C.statBg } },
          alignment: { horizontal: 'left', vertical: 'center' },
          border: this._thinBorder(C.border)
        });
        setStyle(valueRowIdx, startCol, {
          font: { bold: true, sz: 16, color: { rgb: C.navy } },
          fill: { fgColor: { rgb: C.statBg } },
          alignment: { horizontal: 'left', vertical: 'center' },
          border: this._thinBorder(C.border)
        });
      });

      r += 3;
    }

    // HEADER ROW
    sheetData.push(columns);
    const headerRowIdx = r;
    columns.forEach((_, c) => {
      setStyle(headerRowIdx, c, {
        font: { bold: true, sz: 11, color: { rgb: C.headerText } },
        fill: { fgColor: { rgb: C.headerBg } },
        alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
        border: this._thinBorder(C.border)
      });
    });
    r++;

    // BODY ROWS
    const statusColIdx = statusColumn ? columns.indexOf(statusColumn) : -1;

    rows.forEach((row, i) => {
      sheetData.push(row);
      const rowIdx = r + i;
      const zebra = i % 2 === 1 ? 'F8FAFC' : 'FFFFFF';

      row.forEach((_, c) => {
        let style = {
          font: { sz: 10, color: { rgb: '1F2937' } },
          fill: { fgColor: { rgb: zebra } },
          alignment: { vertical: 'center', wrapText: true },
          border: this._thinBorder(C.border)
        };

        if (c === statusColIdx) {
          const key = String(row[c] || '').toLowerCase().replace(/\s+/g, '_');
          const tone = C[key];
          if (tone) {
            style = {
              font: { bold: true, sz: 10, color: { rgb: tone.fg } },
              fill: { fgColor: { rgb: tone.bg } },
              alignment: { horizontal: 'center', vertical: 'center' },
              border: this._thinBorder(C.border)
            };
          }
        }

        setStyle(rowIdx, c, style);
      });
    });

    // BUILD SHEET
    const ws = XLSX.utils.aoa_to_sheet(sheetData);
    ws['!cols'] = columns.map(() => ({ wch: colWidth }));
    ws['!rows'] = sheetData.map((_, i) => (i === (title ? 0 : -1) ? { hpt: 26 } : { hpt: 20 }));
    if (merges.length) ws['!merges'] = merges;

    Object.entries(cellStyles).forEach(([key, style]) => {
      const [row, col] = key.split('_').map(Number);
      const addr = XLSX.utils.encode_cell({ r: row, c: col });
      if (!ws[addr]) ws[addr] = { t: 's', v: '' };
      ws[addr].s = style;
    });

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, sheetName);

    const today = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `${fileName}_${today}.xlsx`);
  }
};

window.EHeartExport = EHeartExport;