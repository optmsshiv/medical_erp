/* ==========================================================================
   Optms Rx — Demo Data Store (Stage 1)
   --------------------------------------------------------------------------
   Shaped to mirror future MySQL tables so Stage 2 (PHP/PDO) can return the
   same structures from REST endpoints. All money values are in INR.
   ========================================================================== */

window.MF_DATA = (function () {

    /* ---------------- Master: Manufacturers ---------------- */
    const manufacturers = [
        'Sun Pharma', 'Cipla', "Dr. Reddy's", 'Mankind', 'Abbott', 'Alkem',
        'Lupin', 'Zydus', 'Glenmark', 'Torrent', 'USV', 'GSK Pharma',
        'Alembic', 'Micro Labs', 'Pfizer', 'Bayer', 'Sanofi', 'FDC Ltd', 'J&J'
    ];

    /* ---------------- Master: Categories ---------------- */
    const categories = [
        'Analgesic & Antipyretic', 'Antibiotic', 'Antacid & PPI', 'Cardiovascular',
        'Antidiabetic', 'Antihistamine', 'Respiratory', 'Nutraceutical',
        'Rehydration (ORS)', 'Antiemetic', 'Hormones & Insulin', 'Topical & Dermatology'
    ];

    /* ---------------- Master: Medicines (medicines table) ----------------
       stock is derived from batches via MF.stockOf() — never hard-coded.    */
    const medicines = [
        { id: 'M01', name: 'Paracetamol 500mg', generic: 'Paracetamol', composition: 'Paracetamol 500mg', category: 'Analgesic & Antipyretic', manufacturer: 'GSK Pharma', brandRef: 'Crocin Advance', hsn: '3004', gst: 12, unit: 'Strip', packSize: '15 Tablets', mrp: 25.00, purchaseRate: 18.90, wholesaleRate: 21.50, minStock: 500, reorderLevel: 600, schedule: 'OTC', rxRequired: false, status: 'Active' },
        { id: 'M02', name: 'Paracetamol 650mg', generic: 'Paracetamol', composition: 'Paracetamol 650mg', category: 'Analgesic & Antipyretic', manufacturer: 'Micro Labs', brandRef: 'Dolo 650', hsn: '3004', gst: 12, unit: 'Strip', packSize: '15 Tablets', mrp: 32.00, purchaseRate: 24.10, wholesaleRate: 27.60, minStock: 150, reorderLevel: 300, schedule: 'OTC', rxRequired: false, status: 'Active' },
        { id: 'M03', name: 'Azithromycin 500mg', generic: 'Azithromycin', composition: 'Azithromycin 500mg', category: 'Antibiotic', manufacturer: 'Alembic', brandRef: 'Azithral 500', hsn: '3004', gst: 12, unit: 'Strip', packSize: '5 Tablets', mrp: 119.50, purchaseRate: 89.75, wholesaleRate: 103.00, minStock: 75, reorderLevel: 120, schedule: 'H', rxRequired: true, status: 'Active' },
        { id: 'M04', name: 'Pantoprazole 40mg', generic: 'Pantoprazole', composition: 'Pantoprazole 40mg', category: 'Antacid & PPI', manufacturer: 'Alkem', brandRef: 'Pan 40', hsn: '3004', gst: 12, unit: 'Strip', packSize: '15 Tablets', mrp: 158.40, purchaseRate: 119.20, wholesaleRate: 136.50, minStock: 100, reorderLevel: 200, schedule: 'H', rxRequired: false, status: 'Active' },
        { id: 'M05', name: 'Amoxicillin 500mg', generic: 'Amoxicillin', composition: 'Amoxicillin 500mg', category: 'Antibiotic', manufacturer: 'Alkem', brandRef: 'Mox 500', hsn: '3004', gst: 12, unit: 'Strip', packSize: '10 Capsules', mrp: 98.00, purchaseRate: 73.50, wholesaleRate: 84.25, minStock: 120, reorderLevel: 200, schedule: 'H', rxRequired: true, status: 'Active' },
        { id: 'M06', name: 'Telmisartan 40mg', generic: 'Telmisartan', composition: 'Telmisartan 40mg', category: 'Cardiovascular', manufacturer: 'Glenmark', brandRef: 'Telma 40', hsn: '3004', gst: 12, unit: 'Strip', packSize: '15 Tablets', mrp: 245.00, purchaseRate: 184.00, wholesaleRate: 211.00, minStock: 75, reorderLevel: 150, schedule: 'H', rxRequired: false, status: 'Active' },
        { id: 'M07', name: 'Amlodipine 5mg', generic: 'Amlodipine', composition: 'Amlodipine Besylate 5mg', category: 'Cardiovascular', manufacturer: "Dr. Reddy's", brandRef: 'Stamlo 5', hsn: '3004', gst: 12, unit: 'Strip', packSize: '15 Tablets', mrp: 48.00, purchaseRate: 36.10, wholesaleRate: 41.50, minStock: 100, reorderLevel: 200, schedule: 'H', rxRequired: false, status: 'Active' },
        { id: 'M08', name: 'Metformin 500mg', generic: 'Metformin', composition: 'Metformin HCl 500mg', category: 'Antidiabetic', manufacturer: 'USV', brandRef: 'Glycomet 500', hsn: '3004', gst: 12, unit: 'Strip', packSize: '20 Tablets', mrp: 62.00, purchaseRate: 46.60, wholesaleRate: 53.40, minStock: 150, reorderLevel: 300, schedule: 'H', rxRequired: false, status: 'Active' },
        { id: 'M09', name: 'Cetirizine 10mg', generic: 'Cetirizine', composition: 'Cetirizine HCl 10mg', category: 'Antihistamine', manufacturer: 'Cipla', brandRef: 'Okacet', hsn: '3004', gst: 12, unit: 'Strip', packSize: '10 Tablets', mrp: 28.00, purchaseRate: 21.05, wholesaleRate: 24.15, minStock: 150, reorderLevel: 300, schedule: 'OTC', rxRequired: false, status: 'Active' },
        { id: 'M10', name: 'ORS Sachet', generic: 'Oral Rehydration Salts', composition: 'ORS WHO Formula 21.8g', category: 'Rehydration (ORS)', manufacturer: 'FDC Ltd', brandRef: 'Electral', hsn: '3004', gst: 12, unit: 'Sachet', packSize: '21.8 g', mrp: 22.00, purchaseRate: 16.50, wholesaleRate: 19.00, minStock: 80, reorderLevel: 160, schedule: 'OTC', rxRequired: false, status: 'Active' },
        { id: 'M11', name: 'Vitamin B Complex', generic: 'B-Complex + Vitamin C', composition: 'Vit B1/B2/B6/B12 + Niacinamide + Vit C', category: 'Nutraceutical', manufacturer: 'Pfizer', brandRef: 'Becosules', hsn: '2106', gst: 18, unit: 'Strip', packSize: '20 Capsules', mrp: 52.00, purchaseRate: 39.10, wholesaleRate: 44.80, minStock: 60, reorderLevel: 120, schedule: 'OTC', rxRequired: false, status: 'Active' },
        { id: 'M12', name: 'Omeprazole 20mg', generic: 'Omeprazole', composition: 'Omeprazole 20mg', category: 'Antacid & PPI', manufacturer: "Dr. Reddy's", brandRef: 'Omez 20', hsn: '3004', gst: 12, unit: 'Strip', packSize: '15 Capsules', mrp: 78.50, purchaseRate: 59.00, wholesaleRate: 67.75, minStock: 100, reorderLevel: 200, schedule: 'H', rxRequired: false, status: 'Active' },
        { id: 'M13', name: 'Cefixime 200mg', generic: 'Cefixime', composition: 'Cefixime 200mg', category: 'Antibiotic', manufacturer: 'Alkem', brandRef: 'Taxim-O 200', hsn: '3004', gst: 12, unit: 'Strip', packSize: '10 Tablets', mrp: 145.00, purchaseRate: 108.90, wholesaleRate: 125.00, minStock: 60, reorderLevel: 120, schedule: 'H', rxRequired: true, status: 'Active' },
        { id: 'M14', name: 'Montelukast + Levocetirizine', generic: 'Montelukast + Levocetirizine', composition: 'Montelukast 10mg + Levocetirizine 5mg', category: 'Respiratory', manufacturer: 'Sun Pharma', brandRef: 'Montek LC', hsn: '3004', gst: 12, unit: 'Strip', packSize: '15 Tablets', mrp: 189.00, purchaseRate: 141.90, wholesaleRate: 162.75, minStock: 60, reorderLevel: 120, schedule: 'H', rxRequired: false, status: 'Active' },
        { id: 'M15', name: 'Rosuvastatin 10mg', generic: 'Rosuvastatin', composition: 'Rosuvastatin Calcium 10mg', category: 'Cardiovascular', manufacturer: 'Sun Pharma', brandRef: 'Rosuvas 10', hsn: '3004', gst: 12, unit: 'Strip', packSize: '15 Tablets', mrp: 298.00, purchaseRate: 223.50, wholesaleRate: 256.70, minStock: 50, reorderLevel: 100, schedule: 'H', rxRequired: false, status: 'Active' },
        { id: 'M16', name: 'Atorvastatin 10mg', generic: 'Atorvastatin', composition: 'Atorvastatin Calcium 10mg', category: 'Cardiovascular', manufacturer: 'Zydus', brandRef: 'Atorva 10', hsn: '3004', gst: 12, unit: 'Strip', packSize: '15 Tablets', mrp: 148.00, purchaseRate: 111.20, wholesaleRate: 127.50, minStock: 60, reorderLevel: 120, schedule: 'H', rxRequired: false, status: 'Active' },
        { id: 'M17', name: 'Cough Syrup 100ml', generic: 'Diphenhydramine + Ammonium Chloride', composition: 'Diphenhydramine + NH4Cl + Menthol', category: 'Respiratory', manufacturer: 'J&J', brandRef: 'Benadryl Syrup', hsn: '3004', gst: 12, unit: 'Bottle', packSize: '100 ml', mrp: 135.00, purchaseRate: 101.40, wholesaleRate: 116.40, minStock: 40, reorderLevel: 80, schedule: 'OTC', rxRequired: false, status: 'Active' },
        { id: 'M18', name: 'Volini Gel 30g', generic: 'Diclofenac Topical', composition: 'Diclofenac 1% + Menthol + Linseed', category: 'Topical & Dermatology', manufacturer: 'Sun Pharma', brandRef: 'Volini', hsn: '3004', gst: 12, unit: 'Tube', packSize: '30 g', mrp: 145.00, purchaseRate: 108.90, wholesaleRate: 125.00, minStock: 30, reorderLevel: 60, schedule: 'OTC', rxRequired: false, status: 'Active' },
        { id: 'M19', name: 'Insulin Glargine 100IU', generic: 'Insulin Glargine', composition: 'Insulin Glargine 100 IU/ml', category: 'Hormones & Insulin', manufacturer: 'Sanofi', brandRef: 'Lantus Solostar', hsn: '3004', gst: 12, unit: 'Pen', packSize: '3 ml Prefilled Pen', mrp: 1385.00, purchaseRate: 1108.00, wholesaleRate: 1270.00, minStock: 12, reorderLevel: 24, schedule: 'H1', rxRequired: true, coldChain: true, status: 'Active' },
        { id: 'M20', name: 'Ondansetron 4mg', generic: 'Ondansetron', composition: 'Ondansetron 4mg', category: 'Antiemetic', manufacturer: 'Cipla', brandRef: 'Emeset 4', hsn: '3004', gst: 12, unit: 'Strip', packSize: '10 Tablets', mrp: 54.00, purchaseRate: 40.55, wholesaleRate: 46.50, minStock: 60, reorderLevel: 120, schedule: 'H', rxRequired: true, status: 'Active' },
        { id: 'M21', name: 'Domperidone 10mg', generic: 'Domperidone', composition: 'Domperidone 10mg', category: 'Antiemetic', manufacturer: 'Alkem', brandRef: 'Domstal 10', hsn: '3004', gst: 12, unit: 'Strip', packSize: '10 Tablets', mrp: 46.00, purchaseRate: 34.55, wholesaleRate: 39.65, minStock: 60, reorderLevel: 120, schedule: 'H', rxRequired: false, status: 'Active' },
        { id: 'M22', name: 'Calcium + Vitamin D3', generic: 'Calcium Carbonate + D3', composition: 'CaCO3 500mg + Vit D3 250 IU', category: 'Nutraceutical', manufacturer: 'Torrent', brandRef: 'Shelcal 500', hsn: '2106', gst: 18, unit: 'Strip', packSize: '15 Tablets', mrp: 112.00, purchaseRate: 84.10, wholesaleRate: 96.50, minStock: 80, reorderLevel: 160, schedule: 'OTC', rxRequired: false, status: 'Active' },
        { id: 'M23', name: 'Amoxiclav 625mg', generic: 'Amoxicillin + Clavulanic Acid', composition: 'Amoxicillin 500mg + Clavulanate 125mg', category: 'Antibiotic', manufacturer: 'GSK Pharma', brandRef: 'Augmentin 625', hsn: '3004', gst: 12, unit: 'Strip', packSize: '10 Tablets', mrp: 225.00, purchaseRate: 169.00, wholesaleRate: 193.90, minStock: 50, reorderLevel: 100, schedule: 'H', rxRequired: true, status: 'Active' },
        { id: 'M24', name: 'Salbutamol Inhaler', generic: 'Salbutamol', composition: 'Salbutamol 100mcg/MDI', category: 'Respiratory', manufacturer: 'Cipla', brandRef: 'Asthalin Inhaler', hsn: '3004', gst: 12, unit: 'Inhaler', packSize: '200 MDI', mrp: 168.00, purchaseRate: 126.15, wholesaleRate: 144.75, minStock: 25, reorderLevel: 50, schedule: 'H', rxRequired: true, status: 'Active' },
        { id: 'M25', name: 'Pantoprazole + Domperidone', generic: 'Pantoprazole + Domperidone', composition: 'Pantoprazole 40mg + Domperidone 30mg SR', category: 'Antacid & PPI', manufacturer: 'Alkem', brandRef: 'Pan D', hsn: '3004', gst: 12, unit: 'Strip', packSize: '15 Capsules', mrp: 198.00, purchaseRate: 148.70, wholesaleRate: 170.60, minStock: 60, reorderLevel: 120, schedule: 'H', rxRequired: false, status: 'Active' },
        { id: 'M26', name: 'Multivitamin Daily', generic: 'Multivitamin + Multiminerals', composition: 'Vit A/B/C/D/E + Zinc + Iron', category: 'Nutraceutical', manufacturer: 'Bayer', brandRef: 'Supradyn', hsn: '2106', gst: 18, unit: 'Strip', packSize: '15 Tablets', mrp: 68.00, purchaseRate: 51.10, wholesaleRate: 58.60, minStock: 90, reorderLevel: 180, schedule: 'OTC', rxRequired: false, status: 'Active' }
    ];

    /* ---------------- Batch-wise stock (stock_batches table) ---------------- */
    const batches = [
        /* — Expired — */
        { id: 'B01', medId: 'M17', batchNo: 'BNY25A11', purchaseDate: '2025-07-15', expiry: '2026-06-30', purchaseRate: 101.40, mrp: 135.00, qty: 38, reserved: 0 },
        { id: 'B02', medId: 'M19', batchNo: 'LAN25B02', purchaseDate: '2025-09-05', expiry: '2026-07-31', purchaseRate: 1108.00, mrp: 1385.00, qty: 6, reserved: 0 },
        { id: 'B03', medId: 'M05', batchNo: 'MOX25B19', purchaseDate: '2025-10-12', expiry: '2026-08-31', purchaseRate: 73.50, mrp: 98.00, qty: 52, reserved: 0 },
        { id: 'B04', medId: 'M22', batchNo: 'SHL25A07', purchaseDate: '2025-08-20', expiry: '2026-08-31', purchaseRate: 84.10, mrp: 112.00, qty: 96, reserved: 0 },
        { id: 'B05', medId: 'M12', batchNo: 'OME25C04', purchaseDate: '2025-09-18', expiry: '2026-07-31', purchaseRate: 59.00, mrp: 78.50, qty: 84, reserved: 0 },
        { id: 'B06', medId: 'M14', batchNo: 'MKT25B22', purchaseDate: '2025-10-02', expiry: '2026-08-31', purchaseRate: 141.90, mrp: 189.00, qty: 62, reserved: 0 },
        { id: 'B07', medId: 'M11', batchNo: 'BCS25A15', purchaseDate: '2025-08-11', expiry: '2026-08-31', purchaseRate: 39.10, mrp: 52.00, qty: 118, reserved: 0 },
        { id: 'B08', medId: 'M21', batchNo: 'DOM25B30', purchaseDate: '2025-09-25', expiry: '2026-07-31', purchaseRate: 34.55, mrp: 46.00, qty: 130, reserved: 0 },
        { id: 'B09', medId: 'M04', batchNo: 'PAN25C08', purchaseDate: '2025-09-30', expiry: '2026-07-31', purchaseRate: 119.20, mrp: 158.40, qty: 14, reserved: 0 },
        /* — Expiring within 30 days — */
        { id: 'B10', medId: 'M03', batchNo: 'AZI25D02', purchaseDate: '2025-10-05', expiry: '2026-09-30', purchaseRate: 89.75, mrp: 119.50, qty: 18, reserved: 2 },
        { id: 'B11', medId: 'M07', batchNo: 'STM25C18', purchaseDate: '2025-10-20', expiry: '2026-10-05', purchaseRate: 36.10, mrp: 48.00, qty: 130, reserved: 0 },
        { id: 'B12', medId: 'M20', batchNo: 'EMS25B09', purchaseDate: '2025-10-08', expiry: '2026-09-25', purchaseRate: 40.55, mrp: 54.00, qty: 200, reserved: 0 },
        { id: 'B13', medId: 'M18', batchNo: 'VOL25A26', purchaseDate: '2025-11-15', expiry: '2026-10-08', purchaseRate: 108.90, mrp: 145.00, qty: 44, reserved: 0 },
        { id: 'B14', medId: 'M10', batchNo: 'ORS25C31', purchaseDate: '2025-11-02', expiry: '2026-10-02', purchaseRate: 16.50, mrp: 22.00, qty: 260, reserved: 0 },
        { id: 'B15', medId: 'M25', batchNo: 'PND25B14', purchaseDate: '2025-09-19', expiry: '2026-09-20', purchaseRate: 148.70, mrp: 198.00, qty: 20, reserved: 0 },
        /* — Expiring within 60 days — */
        { id: 'B16', medId: 'M08', batchNo: 'GLY25C21', purchaseDate: '2025-11-21', expiry: '2026-10-31', purchaseRate: 46.60, mrp: 62.00, qty: 190, reserved: 0 },
        { id: 'B17', medId: 'M16', batchNo: 'ATV25B27', purchaseDate: '2025-12-05', expiry: '2026-11-05', purchaseRate: 111.20, mrp: 148.00, qty: 84, reserved: 0 },
        { id: 'B18', medId: 'M26', batchNo: 'SPR25C09', purchaseDate: '2025-11-28', expiry: '2026-10-28', purchaseRate: 51.10, mrp: 68.00, qty: 96, reserved: 0 },
        { id: 'B19', medId: 'M13', batchNo: 'TXM25B16', purchaseDate: '2025-12-02', expiry: '2026-11-02', purchaseRate: 108.90, mrp: 145.00, qty: 52, reserved: 0 },
        { id: 'B20', medId: 'M09', batchNo: 'OKC25C33', purchaseDate: '2025-11-22', expiry: '2026-10-22', purchaseRate: 21.05, mrp: 28.00, qty: 160, reserved: 0 },
        /* — Expiring within 90 days — */
        { id: 'B21', medId: 'M24', batchNo: 'AST25B08', purchaseDate: '2025-12-25', expiry: '2026-11-25', purchaseRate: 126.15, mrp: 168.00, qty: 60, reserved: 0 },
        { id: 'B22', medId: 'M01', batchNo: 'PCM26A01', purchaseDate: '2026-01-10', expiry: '2026-11-30', purchaseRate: 18.90, mrp: 25.00, qty: 240, reserved: 6 },
        { id: 'B23', medId: 'M02', batchNo: 'DOL25C19', purchaseDate: '2025-12-19', expiry: '2026-12-05', purchaseRate: 24.10, mrp: 32.00, qty: 110, reserved: 0 },
        { id: 'B24', medId: 'M23', batchNo: 'AUG25B25', purchaseDate: '2025-12-18', expiry: '2026-11-18', purchaseRate: 169.00, mrp: 225.00, qty: 40, reserved: 0 },
        { id: 'B25', medId: 'M19', batchNo: 'LAN25C09', purchaseDate: '2026-01-09', expiry: '2026-12-08', purchaseRate: 1108.00, mrp: 1385.00, qty: 12, reserved: 0 },
        { id: 'B26', medId: 'M15', batchNo: 'ROS25C21', purchaseDate: '2025-12-21', expiry: '2026-12-05', purchaseRate: 223.50, mrp: 298.00, qty: 46, reserved: 0 },
        { id: 'B27', medId: 'M06', batchNo: 'TEL25C12', purchaseDate: '2025-12-12', expiry: '2026-12-01', purchaseRate: 184.00, mrp: 245.00, qty: 10, reserved: 0 },
        /* — Healthy stock — */
        { id: 'B28', medId: 'M01', batchNo: 'PCM24A12', purchaseDate: '2026-02-14', expiry: '2027-06-30', purchaseRate: 18.90, mrp: 25.00, qty: 240, reserved: 0 },
        { id: 'B29', medId: 'M02', batchNo: 'PCM24B14', purchaseDate: '2026-01-20', expiry: '2027-03-31', purchaseRate: 24.10, mrp: 32.00, qty: 220, reserved: 0 },
        { id: 'B30', medId: 'M03', batchNo: 'AZI26A01', purchaseDate: '2026-03-15', expiry: '2027-08-31', purchaseRate: 89.75, mrp: 119.50, qty: 40, reserved: 0 },
        { id: 'B31', medId: 'M04', batchNo: 'PAN26B03', purchaseDate: '2026-04-08', expiry: '2027-09-30', purchaseRate: 119.20, mrp: 158.40, qty: 28, reserved: 0 },
        { id: 'B32', medId: 'M05', batchNo: 'MOX26A11', purchaseDate: '2026-03-22', expiry: '2027-07-31', purchaseRate: 73.50, mrp: 98.00, qty: 13, reserved: 0 },
        { id: 'B33', medId: 'M06', batchNo: 'TEL26A05', purchaseDate: '2026-03-05', expiry: '2027-05-31', purchaseRate: 184.00, mrp: 245.00, qty: 20, reserved: 0 },
        { id: 'B34', medId: 'M07', batchNo: 'STM26B02', purchaseDate: '2026-04-02', expiry: '2027-08-31', purchaseRate: 36.10, mrp: 48.00, qty: 220, reserved: 0 },
        { id: 'B35', medId: 'M08', batchNo: 'GLY26A18', purchaseDate: '2026-03-18', expiry: '2027-06-30', purchaseRate: 46.60, mrp: 62.00, qty: 450, reserved: 0 },
        { id: 'B36', medId: 'M09', batchNo: 'OKC26B07', purchaseDate: '2026-05-07', expiry: '2027-04-30', purchaseRate: 21.05, mrp: 28.00, qty: 520, reserved: 0 },
        { id: 'B37', medId: 'M10', batchNo: 'ORS26A09', purchaseDate: '2026-02-09', expiry: '2027-03-31', purchaseRate: 16.50, mrp: 22.00, qty: 300, reserved: 0 },
        { id: 'B38', medId: 'M11', batchNo: 'BCS26B01', purchaseDate: '2026-04-01', expiry: '2027-02-28', purchaseRate: 39.10, mrp: 52.00, qty: 180, reserved: 0 },
        { id: 'B39', medId: 'M12', batchNo: 'OME26A14', purchaseDate: '2026-03-14', expiry: '2027-05-31', purchaseRate: 59.00, mrp: 78.50, qty: 300, reserved: 0 },
        { id: 'B40', medId: 'M14', batchNo: 'MKT26A06', purchaseDate: '2026-02-06', expiry: '2027-04-30', purchaseRate: 141.90, mrp: 189.00, qty: 140, reserved: 0 },
        { id: 'B41', medId: 'M15', batchNo: 'ROS26B08', purchaseDate: '2026-05-08', expiry: '2027-07-31', purchaseRate: 223.50, mrp: 298.00, qty: 110, reserved: 0 },
        { id: 'B42', medId: 'M16', batchNo: 'ATV26A04', purchaseDate: '2026-02-04', expiry: '2027-04-30', purchaseRate: 111.20, mrp: 148.00, qty: 70, reserved: 0 },
        { id: 'B43', medId: 'M17', batchNo: 'BNY26A03', purchaseDate: '2026-01-03', expiry: '2027-01-31', purchaseRate: 101.40, mrp: 135.00, qty: 80, reserved: 0 },
        { id: 'B44', medId: 'M19', batchNo: 'LAN26A01', purchaseDate: '2026-03-01', expiry: '2027-03-31', purchaseRate: 1108.00, mrp: 1385.00, qty: 10, reserved: 0 },
        { id: 'B45', medId: 'M20', batchNo: 'EMS26A12', purchaseDate: '2026-03-12', expiry: '2027-05-31', purchaseRate: 40.55, mrp: 54.00, qty: 200, reserved: 0 },
        { id: 'B46', medId: 'M21', batchNo: 'DOM26B05', purchaseDate: '2026-05-05', expiry: '2027-06-30', purchaseRate: 34.55, mrp: 46.00, qty: 150, reserved: 0 },
        { id: 'B47', medId: 'M22', batchNo: 'SHL26A10', purchaseDate: '2026-03-10', expiry: '2027-05-31', purchaseRate: 84.10, mrp: 112.00, qty: 260, reserved: 0 },
        { id: 'B48', medId: 'M23', batchNo: 'AUG26A03', purchaseDate: '2026-02-03', expiry: '2027-04-30', purchaseRate: 169.00, mrp: 225.00, qty: 6, reserved: 0 },
        { id: 'B49', medId: 'M24', batchNo: 'AST26A04', purchaseDate: '2026-02-04', expiry: '2027-02-28', purchaseRate: 126.15, mrp: 168.00, qty: 40, reserved: 0 },
        { id: 'B50', medId: 'M25', batchNo: 'PND26A02', purchaseDate: '2026-01-02', expiry: '2027-04-30', purchaseRate: 148.70, mrp: 198.00, qty: 130, reserved: 0 },
        { id: 'B51', medId: 'M26', batchNo: 'SPR26B11', purchaseDate: '2026-05-11', expiry: '2027-08-31', purchaseRate: 51.10, mrp: 68.00, qty: 320, reserved: 0 },
        { id: 'B52', medId: 'M13', batchNo: 'TXM26A07', purchaseDate: '2026-03-07', expiry: '2027-03-31', purchaseRate: 108.90, mrp: 145.00, qty: 90, reserved: 0 },
        { id: 'B53', medId: 'M18', batchNo: 'VOL26A05', purchaseDate: '2026-02-05', expiry: '2027-03-31', purchaseRate: 108.90, mrp: 145.00, qty: 14, reserved: 0 }
    ];

    /* ---------------- Doctors ---------------- */
    const doctors = [
        { id: 'D01', name: 'Dr. A. K. Sinha', specialty: 'General Physician', reg: 'BMC 45211', clinic: 'Sinha Clinic, Bhatta Bazar' },
        { id: 'D02', name: 'Dr. Meenakshi Jha', specialty: 'Pediatrician', reg: 'BMC 51820', clinic: 'Little Care Child Clinic' },
        { id: 'D03', name: 'Dr. Rajiv Ranjan', specialty: 'Orthopedic Surgeon', reg: 'BMC 38764', clinic: 'Ranjan Ortho Centre' },
        { id: 'D04', name: 'Dr. S. Hussain', specialty: 'Dermatologist', reg: 'BMC 47103', clinic: 'SkinCare Clinic, Line Bazar' },
        { id: 'D05', name: 'Dr. Kavita Sinha', specialty: 'Gynecologist', reg: 'BMC 42230', clinic: 'Matru Chhaya Clinic' },
        { id: 'D06', name: 'Dr. V. K. Mandal', specialty: 'Cardiologist', reg: 'BMC 33918', clinic: 'Heart Care Centre, Purnia' }
    ];

    /* ---------------- Customers ---------------- */
    const customers = [
        { id: 'C01', name: 'Walk-in Customer', type: 'Retail Customer', mobile: '—', gstin: '—', address: 'Counter Sales', totalSales: 648200, paid: 648200, due: 0, lastPurchase: '2026-09-10' },
        { id: 'C02', name: 'Priya Singh', type: 'Retail Customer', mobile: '98352 41276', gstin: '—', address: 'Madhubani, Purnia', totalSales: 12480, paid: 12480, due: 0, lastPurchase: '2026-09-10' },
        { id: 'C03', name: 'Rakesh Roshan', type: 'Retail Customer', mobile: '90064 28815', gstin: '—', address: 'Ram Bagh Colony, Purnia', totalSales: 18215, paid: 18215, due: 0, lastPurchase: '2026-09-10' },
        { id: 'C04', name: 'Sunita Devi', type: 'Retail Customer', mobile: '94314 66209', gstin: '—', address: 'Gulab Bagh, Purnia', totalSales: 8460, paid: 7220, due: 1240, lastPurchase: '2026-09-08' },
        { id: 'C05', name: 'Mohammed Imran', type: 'Retail Customer', mobile: '99348 10573', gstin: '—', address: 'Bhatta Bazar, Purnia', totalSales: 9320, paid: 9320, due: 0, lastPurchase: '2026-09-07' },
        { id: 'C06', name: 'MediMart Distributors', type: 'Wholesale Dealer', mobile: '94310 22841', gstin: '10AABCM1234F1Z5', dlNo: '20B-PUR-112233', address: 'Station Road, Katihar', totalSales: 482300, paid: 412600, due: 69700, lastPurchase: '2026-09-10' },
        { id: 'C07', name: 'Seemanchal Pharma', type: 'Wholesale Dealer', mobile: '98354 77120', gstin: '10AACCS9876K1Z3', dlNo: '20B-PUR-114455', address: 'Kachahari Road, Araria', totalSales: 356800, paid: 272300, due: 84500, lastPurchase: '2026-09-10' },
        { id: 'C08', name: 'Kishanganj Medical Stores', type: 'Wholesale Dealer', mobile: '90062 35518', gstin: '10AAKCK4567M1Z8', dlNo: '20B-KIS-221108', address: 'MG Road, Kishanganj', totalSales: 218400, paid: 180200, due: 38200, lastPurchase: '2026-09-09' },
        { id: 'C09', name: 'Ford Hospital & Research Centre', type: 'Hospital', mobile: '06454-224466', gstin: '10AAATH7890P1Z2', dlNo: '20B-PUR-330021', address: 'Purnia Bypass, NH-31', totalSales: 742600, paid: 617750, due: 124850, lastPurchase: '2026-09-10' },
        { id: 'C10', name: 'Sadar Hospital Pharmacy', type: 'Hospital', mobile: '06454-220118', gstin: '—', dlNo: '20B-PUR-330077', address: 'Hospital Rd, Purnia', totalSales: 186400, paid: 144400, due: 42000, lastPurchase: '2026-09-06' },
        { id: 'C11', name: 'Chouhan Clinic', type: 'Clinic', mobile: '94721 88345', gstin: '—', address: 'Chowk, Purnia', totalSales: 64300, paid: 40300, due: 24000, lastPurchase: '2026-09-05' },
        { id: 'C12', name: 'Jan Seva Kendra', type: 'Other', mobile: '85417 60229', gstin: '—', address: 'Rupauli, Purnia', totalSales: 21850, paid: 21850, due: 0, lastPurchase: '2026-09-03' }
    ];

    /* ---------------- Suppliers ---------------- */
    const suppliers = [
        { id: 'S01', name: 'MedLink Distributors', contact: 'Rakesh Agarwal', mobile: '94312 45670', gstin: '10AABCM2211G1Z4', dlNo: '21B-PUR-770011', address: 'Bhatta Bazar, Purnia', totalPurchase: 1240500, paid: 1105200, due: 135300, lastPurchase: '2026-09-09' },
        { id: 'S02', name: 'Cipla Depot — Patna', contact: 'Sanjay Kumar', mobile: '98353 90218', gstin: '10AAACL3344H1Z6', dlNo: '21B-PAT-550092', address: 'Kankarbagh, Patna', totalPurchase: 864200, paid: 821600, due: 42600, lastPurchase: '2026-09-08' },
        { id: 'S03', name: 'Alkem Distributor — Katihar', contact: 'Munna Gupta', mobile: '94316 20874', gstin: '10AADCA5566J1Z1', dlNo: '21B-KAT-550417', address: 'Mirchaibari, Katihar', totalPurchase: 918600, paid: 918600, due: 0, lastPurchase: '2026-09-07' },
        { id: 'S04', name: 'Sun Pharma Stockist — Patna', contact: 'Prakash Jha', mobile: '90064 78123', gstin: '10AAECS7788K1Z9', dlNo: '21B-PAT-550633', address: 'Boring Road, Patna', totalPurchase: 705400, paid: 646500, due: 58900, lastPurchase: '2026-09-06' },
        { id: 'S05', name: 'Patna Medicos Pvt Ltd', contact: 'Vijay Barnwal', mobile: '98352 11907', gstin: '10AAFCP8899L1Z7', dlNo: '21B-PAT-551208', address: 'Gandhi Maidan, Patna', totalPurchase: 542100, paid: 524100, due: 18000, lastPurchase: '2026-09-05' },
        { id: 'S06', name: 'Bihar Pharma Supplies', contact: 'Anil Sah', mobile: '94301 55862', gstin: '10AAGCB1122M1Z5', dlNo: '21B-PUR-770195', address: 'Maranga, Purnia', totalPurchase: 388700, paid: 366700, due: 22000, lastPurchase: '2026-09-02' },
        { id: 'S07', name: 'Zydus Agency — Bhagalpur', contact: 'Rahul Verma', mobile: '99392 40716', gstin: '10AAHCZ6677N1Z2', dlNo: '21B-BGP-660341', address: 'Tilkamanjhi, Bhagalpur', totalPurchase: 296400, paid: 296400, due: 0, lastPurchase: '2026-08-29' },
        { id: 'S08', name: 'HealthFirst Agencies', contact: 'Dinesh Rathi', mobile: '85410 92263', gstin: '10AAICH9900P1Z8', dlNo: '21B-PUR-770286', address: 'Line Bazar, Purnia', totalPurchase: 214800, paid: 214800, due: 0, lastPurchase: '2026-08-25' }
    ];

    /* ---------------- Sales invoices ---------------- */
    const salesInvoices = [
        { no: 'INV-26-01247', customerId: 'C01', customer: 'Walk-in Customer', date: '2026-09-10', time: '12:18 PM', type: 'Retail', amount: 1248, tax: 134, discount: 40, payment: 'UPI', status: 'Paid' },
        { no: 'INV-26-01246', customerId: 'C06', customer: 'MediMart Distributors', date: '2026-09-10', time: '11:47 AM', type: 'Wholesale', amount: 42860, tax: 6538, discount: 2140, payment: 'Credit', status: 'Due' },
        {
            no: 'INV-26-01245', customerId: 'C02', customer: 'Priya Singh', date: '2026-09-10', time: '11:12 AM', type: 'Retail', amount: 864, tax: 93, discount: 0, payment: 'Cash', status: 'Paid',
            items: [{ medId: 'M02', batch: 'PCM24B14', name: 'Paracetamol 650mg', qty: 2, rate: 32.00, gst: 12 }, { medId: 'M10', batch: 'ORS25C31', name: 'ORS Sachet', qty: 4, rate: 22.00, gst: 12 }, { medId: 'M11', batch: 'BCS26B01', name: 'Vitamin B Complex', qty: 1, rate: 52.00, gst: 18 }, { medId: 'M22', batch: 'SHL26A10', name: 'Calcium + Vitamin D3', qty: 5, rate: 112.00, gst: 18 }]
        },
        { no: 'INV-26-01244', customerId: 'C09', customer: 'Ford Hospital & Research Centre', date: '2026-09-10', time: '10:36 AM', type: 'Wholesale', amount: 18420, tax: 2810, discount: 920, payment: 'Bank', status: 'Paid' },
        {
            no: 'INV-26-01243', customerId: 'C03', customer: 'Rakesh Roshan', date: '2026-09-10', time: '10:05 AM', type: 'Retail', amount: 2315, tax: 248, discount: 85, payment: 'Card', status: 'Paid',
            items: [{ medId: 'M04', batch: 'PAN26B03', name: 'Pantoprazole 40mg', qty: 3, rate: 158.40, gst: 12 }, { medId: 'M03', batch: 'AZI25D02', name: 'Azithromycin 500mg', qty: 2, rate: 119.50, gst: 12 }, { medId: 'M14', batch: 'MKT26A06', name: 'Montelukast + Levocetirizine', qty: 4, rate: 189.00, gst: 12 }, { medId: 'M09', batch: 'OKC26B07', name: 'Cetirizine 10mg', qty: 6, rate: 28.00, gst: 12 }]
        },
        { no: 'INV-26-01242', customerId: 'C07', customer: 'Seemanchal Pharma', date: '2026-09-10', time: '09:52 AM', type: 'Wholesale', amount: 27940, tax: 4262, discount: 1400, payment: 'Credit', status: 'Partial' },
        { no: 'INV-26-01241', customerId: 'C04', customer: 'Sunita Devi', date: '2026-09-09', time: '07:41 PM', type: 'Retail', amount: 1240, tax: 133, discount: 0, payment: 'Credit', status: 'Due' },
        {
            no: 'INV-26-01240', customerId: 'C05', customer: 'Mohammed Imran', date: '2026-09-09', time: '05:22 PM', type: 'Retail', amount: 3145, tax: 338, discount: 120, payment: 'UPI', status: 'Paid',
            items: [{ medId: 'M19', batch: 'LAN25C09', name: 'Insulin Glargine 100IU', qty: 2, rate: 1385.00, gst: 12 }, { medId: 'M08', batch: 'GLY26A18', name: 'Metformin 500mg', qty: 4, rate: 62.00, gst: 12 }, { medId: 'M26', batch: 'SPR26B11', name: 'Multivitamin Daily', qty: 3, rate: 68.00, gst: 18 }]
        },
        { no: 'INV-26-01239', customerId: 'C08', customer: 'Kishanganj Medical Stores', date: '2026-09-09', time: '02:15 PM', type: 'Wholesale', amount: 51230, tax: 7815, discount: 2560, payment: 'Bank', status: 'Paid' },
        {
            no: 'INV-26-01238', customerId: 'C01', customer: 'Walk-in Customer', date: '2026-09-09', time: '12:48 PM', type: 'Retail', amount: 1890, tax: 203, discount: 60, payment: 'Cash', status: 'Paid',
            items: [{ medId: 'M06', batch: 'TEL25C12', name: 'Telmisartan 40mg', qty: 3, rate: 245.00, gst: 12 }, { medId: 'M15', batch: 'ROS25C21', name: 'Rosuvastatin 10mg', qty: 2, rate: 298.00, gst: 12 }, { medId: 'M07', batch: 'STM26B02', name: 'Amlodipine 5mg', qty: 4, rate: 48.00, gst: 12 }]
        },
        { no: 'INV-26-01237', customerId: 'C10', customer: 'Sadar Hospital Pharmacy', date: '2026-09-08', time: '04:33 PM', type: 'Wholesale', amount: 36480, tax: 5565, discount: 1820, payment: 'Credit', status: 'Due' },
        { no: 'INV-26-01236', customerId: 'C11', customer: 'Chouhan Clinic', date: '2026-09-08', time: '11:26 AM', type: 'Wholesale', amount: 14820, tax: 2261, discount: 740, payment: 'Credit', status: 'Partial' }
    ];

    /* ---------------- Purchase invoices ---------------- */
    const purchaseInvoices = [
        { no: 'GRN-26-0871', supplierId: 'S01', supplier: 'MedLink Distributors', supplierInv: 'ML/2026-27/0456', date: '2026-09-09', items: 24, amount: 68540, tax: 8225, payment: 'Bank', status: 'Paid' },
        {
            no: 'GRN-26-0870', supplierId: 'S02', supplier: 'Cipla Depot — Patna', supplierInv: 'CPL/PT/11842', date: '2026-09-08', items: 12, amount: 24180, tax: 2902, payment: 'Credit', status: 'Due',
            lines: [{ medId: 'M09', name: 'Cetirizine 10mg', batch: 'OKC26C01', expiry: '2028-03-31', qty: 200, rate: 21.05, gst: 12 }, { medId: 'M20', name: 'Ondansetron 4mg', batch: 'EMS26B20', expiry: '2027-12-31', qty: 150, rate: 40.55, gst: 12 }, { medId: 'M24', name: 'Salbutamol Inhaler', batch: 'AST26B11', expiry: '2027-09-30', qty: 60, rate: 126.15, gst: 12 }]
        },
        { no: 'GRN-26-0869', supplierId: 'S03', supplier: 'Alkem Distributor — Katihar', supplierInv: 'ALK/KTR/7745', date: '2026-09-07', items: 18, amount: 51260, tax: 6151, payment: 'UPI', status: 'Paid' },
        { no: 'GRN-26-0868', supplierId: 'S04', supplier: 'Sun Pharma Stockist — Patna', supplierInv: 'SUN/PT/3320', date: '2026-09-06', items: 9, amount: 36900, tax: 4428, payment: 'Credit', status: 'Partial' },
        {
            no: 'GRN-26-0867', supplierId: 'S05', supplier: 'Patna Medicos Pvt Ltd', supplierInv: 'PM/2026/9918', date: '2026-09-05', items: 15, amount: 19740, tax: 2369, payment: 'Cash', status: 'Paid',
            lines: [{ medId: 'M01', name: 'Paracetamol 500mg', batch: 'PCM26C04', expiry: '2028-06-30', qty: 500, rate: 18.90, gst: 12 }, { medId: 'M10', name: 'ORS Sachet', batch: 'ORS26C02', expiry: '2028-01-31', qty: 300, rate: 16.50, gst: 12 }, { medId: 'M21', name: 'Domperidone 10mg', batch: 'DOM26C15', expiry: '2027-11-30', qty: 120, rate: 34.55, gst: 12 }]
        },
        { no: 'GRN-26-0866', supplierId: 'S06', supplier: 'Bihar Pharma Supplies', supplierInv: 'BPS/PUR/2214', date: '2026-09-02', items: 11, amount: 28450, tax: 3414, payment: 'Bank', status: 'Paid' },
        { no: 'GRN-26-0865', supplierId: 'S01', supplier: 'MedLink Distributors', supplierInv: 'ML/2026-27/0412', date: '2026-08-30', items: 20, amount: 58210, tax: 6985, payment: 'Credit', status: 'Due' },
        { no: 'GRN-26-0864', supplierId: 'S07', supplier: 'Zydus Agency — Bhagalpur', supplierInv: 'ZYD/BGP/5580', date: '2026-08-29', items: 7, amount: 16320, tax: 1958, payment: 'Bank', status: 'Paid' }
    ];

    /* ---------------- Dashboard aggregates ---------------- */
    const dashboard = {
        todaySales: 184650, todaySalesDelta: 12.4,
        todayPurchase: 96420, todayPurchaseDelta: -4.8,
        grossProfit: 42780, grossProfitDelta: 8.2,
        stockValue: 1842500, stockValueDelta: 2.1,
        customerDue: 384250, customerDueDelta: 5.6,
        supplierDue: 276800, supplierDueDelta: -3.2,
        salesSplit: { retail: 112400, wholesale: 72250 },
        salesTrend: {
            today: {
                labels: ['9 AM', '10 AM', '11 AM', '12 PM', '1 PM', '2 PM'],
                retail: [8200, 14600, 21800, 18400, 12600, 9800],
                wholesale: [0, 27940, 42860, 18420, 0, 6500]
            },
            '7d': {
                labels: ['Thu', 'Fri', 'Sat', 'Sun', 'Mon', 'Tue', 'Wed'],
                retail: [86400, 92300, 118500, 74200, 88600, 104300, 112400],
                wholesale: [52800, 61400, 84200, 38600, 66900, 79800, 72250]
            },
            '30d': {
                labels: ['W1', 'W2', 'W3', 'W4'],
                retail: [596000, 648000, 621000, 682400],
                wholesale: [384000, 421000, 405000, 447600]
            },
            month: {
                labels: ['1-3 Sep', '4-6 Sep', '7-9 Sep', '10 Sep'],
                retail: [248600, 262400, 274900, 112400],
                wholesale: [158200, 172600, 181400, 72250]
            }
        },
        topSellers: [
            { rank: 1, medId: 'M04', name: 'Pantoprazole 40mg', qty: 141, revenue: 22334 },
            { rank: 2, medId: 'M06', name: 'Telmisartan 40mg', qty: 96, revenue: 23520 },
            { rank: 3, medId: 'M02', name: 'Paracetamol 650mg', qty: 296, revenue: 9472 },
            { rank: 4, medId: 'M08', name: 'Metformin 500mg', qty: 188, revenue: 11656 },
            { rank: 5, medId: 'M01', name: 'Paracetamol 500mg', qty: 342, revenue: 8550 },
            { rank: 6, medId: 'M09', name: 'Cetirizine 10mg', qty: 204, revenue: 5712 }
        ]
    };

    /* ---------------- Notifications ---------------- */
    const notifications = [
        { tone: 'danger', icon: 'calendar-x', title: '1 batch expires in 10 days', body: 'Pan D (PND25B14) — 20 units left', time: '10 min ago' },
        { tone: 'warning', icon: 'exclamation-triangle', title: '6 medicines below reorder level', body: 'Azithromycin 500mg, Pantoprazole 40mg +4 more', time: '1 hr ago' },
        { tone: 'info', icon: 'cash-stack', title: 'Payment pending: ₹42,860', body: 'MediMart Distributors · INV-26-01246 on credit', time: '2 hrs ago' },
        { tone: 'warning', icon: 'wallet2', title: 'Supplier payment due tomorrow', body: 'GRN-26-0870 · Cipla Depot — Patna', time: '5 hrs ago' },
        { tone: 'primary', icon: 'percent', title: 'GSTR-1 filing due 11 Sep', body: 'August 2026 outward supplies summary ready', time: 'Yesterday' }
    ];

    /* ---------------- Users (Administration) ---------------- */
    const users = [
        { id: 'U01', name: 'Rajesh Kumar', role: 'Owner / Admin', mobile: '94310 12345', email: 'rajesh@optmsrx.in', status: 'Active', lastLogin: '2026-09-10 09:12' },
        { id: 'U02', name: 'Amit Verma', role: 'Pharmacist', mobile: '98352 67890', email: 'amit@optmsrx.in', status: 'Active', lastLogin: '2026-09-10 08:55' },
        { id: 'U03', name: 'Pooja Kumari', role: 'Billing Clerk', mobile: '90064 55412', email: 'pooja@optmsrx.in', status: 'Active', lastLogin: '2026-09-09 19:30' },
        { id: 'U04', name: 'Sohan Das', role: 'Purchase Manager', mobile: '94314 80927', email: 'sohan@optmsrx.in', status: 'Inactive', lastLogin: '2026-08-28 11:04' }
    ];

    const auditLogs = [
        { ts: '2026-09-10 12:18', user: 'Amit Verma', action: 'SALE_CREATE', detail: 'INV-26-01247 · ₹1,248 · UPI' },
        { ts: '2026-09-10 11:52', user: 'Pooja Kumari', action: 'WHOLESALE_SALE', detail: 'INV-26-01246 · MediMart Distributors · ₹42,860' },
        { ts: '2026-09-10 09:40', user: 'Rajesh Kumar', action: 'RATE_UPDATE', detail: 'Insulin Glargine MRP ₹1,350 → ₹1,385' },
        { ts: '2026-09-09 18:22', user: 'Sohan Das', action: 'PURCHASE_CREATE', detail: 'GRN-26-0871 · MedLink Distributors · ₹68,540' },
        { ts: '2026-09-09 10:15', user: 'Amit Verma', action: 'STOCK_ADJUST', detail: 'Cough Syrup 100ml · -2 (Damaged bottle)' },
        { ts: '2026-09-08 16:44', user: 'Pooja Kumari', action: 'SALES_RETURN', detail: 'SRN-26-0211 · INV-26-01219 · ₹464 refund' },
        { ts: '2026-09-08 09:05', user: 'Rajesh Kumar', action: 'LOGIN', detail: 'Web · Purnia, Bihar · IP 103.21.58.14' }
    ];

    return { manufacturers, categories, medicines, batches, doctors, customers, suppliers, salesInvoices, purchaseInvoices, dashboard, notifications, users, auditLogs };
})();