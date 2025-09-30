// Evidence přání videií v3.2.O - JavaScript
class EvidenceApp {
    constructor() {
        this.currentUser = null;
        this.currentPage = 1;
        this.currentFilters = {};
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.populateYearSelectors();
        this.checkSession();
        this.hideLoading();
    }

    setupEventListeners() {
        // Login form
        document.getElementById('login-form')?.addEventListener('submit', (e) => this.handleLogin(e));

        // Logout
        document.getElementById('logout-btn')?.addEventListener('click', () => this.handleLogout());

        // Navigation
        document.querySelectorAll('.nav-btn').forEach(btn => {
            btn.addEventListener('click', (e) => this.handleNavigation(e));
        });

        // New record form
        document.getElementById('new-record-form')?.addEventListener('submit', (e) => this.handleNewRecord(e));

        // Filters
        document.getElementById('apply-filters')?.addEventListener('click', () => this.applyFilters());
        document.getElementById('clear-filters')?.addEventListener('click', () => this.clearFilters());

        // Monthly and yearly overviews
        document.getElementById('load-monthly')?.addEventListener('click', () => this.loadMonthlyOverview());
        document.getElementById('load-yearly')?.addEventListener('click', () => this.loadYearlyOverview());

        // Import/Export
        document.getElementById('export-csv')?.addEventListener('click', () => this.exportCSV());
        document.getElementById('import-csv')?.addEventListener('click', () => this.importCSV());

        // User management
        document.getElementById('add-user-btn')?.addEventListener('click', () => this.showUserModal());

        // Print table
        document.getElementById('print-table')?.addEventListener('click', () => this.printTable());

        // Print detail
        document.getElementById('print-detail')?.addEventListener('click', () => this.printRecordDetail());

        // Modal close handlers
        document.querySelectorAll('.close, .close-modal').forEach(btn => {
            btn.addEventListener('click', (e) => this.closeModal(e.target.closest('.modal')));
        });

        // Modal forms
        document.getElementById('edit-record-form')?.addEventListener('submit', (e) => this.handleEditRecord(e));
        document.getElementById('user-form')?.addEventListener('submit', (e) => this.handleUserForm(e));
        document.getElementById('password-form')?.addEventListener('submit', (e) => this.handlePasswordForm(e));

        // Auto focus on jmeno field when new record section is shown
        document.addEventListener('click', (e) => {
            if (e.target.matches('.nav-btn[data-section="new-record"]')) {
                setTimeout(() => {
                    document.getElementById('new-jmeno')?.focus();
                }, 100);
            }
        });

        // Set today's date as default for new records
        const today = new Date().toISOString().split('T')[0];
        const newDatumField = document.getElementById('new-datum');
        if (newDatumField) {
            newDatumField.value = today;
        }
    }

    async apiCall(action, data = null, method = 'GET', urlParams = null) {
        try {
            let url = `api.php?action=${action}`;
            
            // Přidat dodatečné URL parametry
            if (urlParams) {
                const params = new URLSearchParams(urlParams);
                url += '&' + params.toString();
            }
            
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                }
            };

            if (method === 'GET' && data) {
                const params = new URLSearchParams(data);
                url += '&' + params.toString();
            } else if (data) {
                options.body = JSON.stringify(data);
            }

            const response = await fetch(url, options);
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message);
            }

            return result;
        } catch (error) {
            this.showNotification('Chyba', error.message, 'error');
            throw error;
        }
    }

    async checkSession() {
        try {
            const result = await this.apiCall('check-session');
            this.currentUser = result.data;
            this.showMainApp();
        } catch (error) {
            this.showLogin();
        }
    }

    async handleLogin(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        try {
            this.showLoading();
            const result = await this.apiCall('login', {
                username: formData.get('username'),
                password: formData.get('password')
            }, 'POST');
            
            this.currentUser = result.data;
            this.showNotification('Úspěch', 'Přihlášení bylo úspěšné', 'success');
            this.showMainApp();
        } catch (error) {
            // Error already handled in apiCall
        } finally {
            this.hideLoading();
        }
    }

    async handleLogout() {
        try {
            await this.apiCall('logout', null, 'POST');
            this.currentUser = null;
            this.showNotification('Informace', 'Byli jste odhlášeni', 'info');
            this.showLogin();
        } catch (error) {
            // Error already handled in apiCall
        }
    }

    showLogin() {
        document.getElementById('login-container').style.display = 'flex';
        document.getElementById('main-app').style.display = 'none';
    }

    showMainApp() {
        document.getElementById('login-container').style.display = 'none';
        document.getElementById('main-app').style.display = 'flex';
        
        if (this.currentUser) {
            document.getElementById('current-user').textContent = this.currentUser.username;
            
            // Show/hide navigation based on role
            if (this.currentUser.role === 'admin') {
                document.getElementById('users-nav').style.display = 'flex';
                document.getElementById('audit-nav').style.display = 'flex';
                // Admin vidí všechny sekce
                document.querySelector('.nav-btn[data-section="new-record"]').style.display = 'flex';
                document.querySelector('.nav-btn[data-section="import-export"]').style.display = 'flex';
            } else {
                // User role - skryj správu uživatelů, nový zápis a import/export
                document.getElementById('users-nav').style.display = 'none';
                document.getElementById('audit-nav').style.display = 'none';
                document.querySelector('.nav-btn[data-section="new-record"]').style.display = 'none';
                document.querySelector('.nav-btn[data-section="import-export"]').style.display = 'none';
            }
        }
        
        this.loadRecords();
    }

    handleNavigation(e) {
        const targetSection = e.target.closest('.nav-btn').dataset.section;
        
        // Update navigation
        document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
        e.target.closest('.nav-btn').classList.add('active');
        
        // Show target section
        document.querySelectorAll('.section').forEach(section => section.classList.remove('active'));
        document.getElementById(targetSection + '-section').classList.add('active');
        
        // Load section data
        switch (targetSection) {
            case 'dashboard':
                // Dashboard content is loaded via iframe, no additional action needed
                break;
            case 'records':
                this.loadRecords();
                break;
            case 'monthly':
                this.loadMonthlyOverview();
                break;
            case 'yearly':
                this.loadYearlyOverview();
                break;
            case 'users':
                this.loadUsers();
                break;
        }
    }

    async handleNewRecord(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        try {
            this.showLoading();
            
            const data = {};
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            await this.apiCall('create-record', data, 'POST');
            this.showNotification('Úspěch', 'Záznam byl úspěšně vytvořen', 'success');
            
            // Reset form and focus jmeno field
            e.target.reset();
            document.getElementById('new-datum').value = new Date().toISOString().split('T')[0];
            document.getElementById('new-jmeno').focus();
            
            // Reload records if on records section
            if (document.getElementById('records-section').classList.contains('active')) {
                this.loadRecords();
            }
        } catch (error) {
            // Error already handled in apiCall
        } finally {
            this.hideLoading();
        }
    }

    async loadRecords(page = 1) {
        try {
            this.showLoading();
            
            const params = {
                page: page,
                ...this.currentFilters
            };
            
            const result = await this.apiCall('get-records', params);
            this.renderRecords(result.data);
            this.renderPagination(result.data);
            this.currentPage = page;
        } catch (error) {
            // Error already handled in apiCall
        } finally {
            this.hideLoading();
        }
    }

    renderRecords(data) {
        const tbody = document.getElementById('records-tbody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        // Debug log
        console.log('renderRecords data:', data);
        
        if (!data.records || !Array.isArray(data.records)) {
            console.error('Data neobsahují pole records:', data);
            tbody.innerHTML = '<tr><td colspan="11" style="text-align: center; padding: 20px;">Žádná data k zobrazení</td></tr>';
            return;
        }
        
        if (data.records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="11" style="text-align: center; padding: 20px;">Žádné záznamy k zobrazení</td></tr>';
            return;
        }
        
        const isAdmin = this.currentUser?.role === 'admin';
        
        data.records.forEach(record => {
            const row = document.createElement('tr');
            row.className = `status-${record.stav}`;
            
            // Pro uživatele skryjeme částku
            const amountDisplay = isAdmin 
                ? (record.castka ? parseFloat(record.castka).toFixed(2) + ' Kč' : '')
                : '*****';
            
            // Akční tlačítka pouze pro admin
            const actionButtons = isAdmin 
                ? `<div class="action-buttons">
                       <button class="btn btn-sm btn-info" onclick="app.viewRecord(${record.id})" title="Zobrazit detail">
                           <i class="fas fa-eye"></i>
                       </button>
                       <button class="btn btn-sm btn-primary" onclick="app.editRecord(${record.id})" title="Editovat">
                           <i class="fas fa-edit"></i>
                       </button>
                       <button class="btn btn-sm btn-error" onclick="app.deleteRecord(${record.id})" title="Smazat">
                           <i class="fas fa-trash"></i>
                       </button>
                   </div>`
                : `<div class="action-buttons">
                       <button class="btn btn-sm btn-info" onclick="app.viewRecord(${record.id})" title="Zobrazit detail">
                           <i class="fas fa-eye"></i>
                       </button>
                   </div>`;
            
            row.innerHTML = `
                <td>${record.id}</td>
                <td>${record.datum}</td>
                <td>${record.jmeno}</td>
                <td>${record.ucet || ''}</td>
                <td>${amountDisplay}</td>
                <td><span class="status-badge">${record.stav}</span></td>
                <td>${record.prani || ''}</td>
                <td>${record.nick || ''}</td>
                <td>${record.link ? `<a href="${record.link}" target="_blank" class="btn btn-sm btn-secondary"><i class="fas fa-external-link-alt"></i></a>` : ''}</td>
                <td>${record.faktura || ''}</td>
                <td>${actionButtons}</td>
            `;
            
            tbody.appendChild(row);
        });
    }

    renderPagination(data) {
        const pagination = document.getElementById('pagination');
        if (!pagination) return;
        
        pagination.innerHTML = '';
        
        if (data.pages <= 1) return;
        
        // Previous button
        const prevBtn = document.createElement('button');
        prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prevBtn.disabled = data.current_page === 1;
        prevBtn.onclick = () => this.loadRecords(data.current_page - 1);
        pagination.appendChild(prevBtn);
        
        // Page numbers
        for (let i = 1; i <= data.pages; i++) {
            if (i === 1 || i === data.pages || (i >= data.current_page - 2 && i <= data.current_page + 2)) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = i;
                pageBtn.className = data.current_page === i ? 'active' : '';
                pageBtn.onclick = () => this.loadRecords(i);
                pagination.appendChild(pageBtn);
            } else if (i === data.current_page - 3 || i === data.current_page + 3) {
                const ellipsis = document.createElement('span');
                ellipsis.textContent = '...';
                ellipsis.style.padding = '0.5rem';
                pagination.appendChild(ellipsis);
            }
        }
        
        // Next button
        const nextBtn = document.createElement('button');
        nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
        nextBtn.disabled = data.current_page === data.pages;
        nextBtn.onclick = () => this.loadRecords(data.current_page + 1);
        pagination.appendChild(nextBtn);
    }

    applyFilters() {
        this.currentFilters = {
            stav: document.getElementById('stav-filter').value,
            prani: document.getElementById('prani-filter').value,
            nick: document.getElementById('nick-filter').value,
            datum_od: document.getElementById('datum-od-filter').value,
            datum_do: document.getElementById('datum-do-filter').value
        };
        
        this.loadRecords(1);
    }

    clearFilters() {
        document.getElementById('stav-filter').value = '';
        document.getElementById('prani-filter').value = '';
        document.getElementById('nick-filter').value = '';
        document.getElementById('datum-od-filter').value = '';
        document.getElementById('datum-do-filter').value = '';
        
        this.currentFilters = {};
        this.loadRecords(1);
    }

    async editRecord(id) {
        try {
            // Load record data first
            const result = await this.apiCall('get-records', { id: id });
            const record = result.data.records.find(r => r.id == id);
            
            if (record) {
                // Populate edit form
                document.getElementById('edit-record-id').value = record.id;
                document.getElementById('edit-datum').value = record.datum;
                document.getElementById('edit-jmeno').value = record.jmeno;
                document.getElementById('edit-ucet').value = record.ucet || '';
                document.getElementById('edit-castka').value = record.castka || '';
                document.getElementById('edit-stav').value = record.stav;
                document.getElementById('edit-prani').value = record.prani || '';
                document.getElementById('edit-nick').value = record.nick || '';
                document.getElementById('edit-link').value = record.link || '';
                document.getElementById('edit-faktura').value = record.faktura || '';
                
                this.showModal('edit-record-modal');
            }
        } catch (error) {
            // Error already handled in apiCall
        }
    }

    async handleEditRecord(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        try {
            this.showLoading();
            
            const data = {};
            for (let [key, value] of formData.entries()) {
                if (key !== 'id') {
                    data[key] = value;
                }
            }
            
            const id = formData.get('id');
            await this.apiCall('update-record', data, 'POST', { id: id });
            
            this.showNotification('Úspěch', 'Záznam byl úspěšně aktualizován', 'success');
            this.closeModal(document.getElementById('edit-record-modal'));
            this.loadRecords(this.currentPage);
        } catch (error) {
            // Error already handled in apiCall
        } finally {
            this.hideLoading();
        }
    }

    async deleteRecord(id) {
        if (confirm('Opravdu chcete smazat tento záznam?')) {
            try {
                this.showLoading();
                await this.apiCall('delete-record', null, 'POST', { id: id });
                this.showNotification('Úspěch', 'Záznam byl smazán', 'success');
                this.loadRecords(this.currentPage);
            } catch (error) {
                // Error already handled in apiCall
            } finally {
                this.hideLoading();
            }
        }
    }

    async loadMonthlyOverview() {
        try {
            const year = document.getElementById('monthly-year').value;
            const month = document.getElementById('monthly-month').value;
            
            if (!year || !month) return;
            
            this.showLoading();
            
            // Načtení přehledu podle stavů
            const overviewResult = await this.apiCall('monthly-overview', { year, month });
            
            // Načtení denního detailu
            const detailResult = await this.apiCall('monthly-detail', { year, month });
            
            this.renderMonthlyOverview(overviewResult.data, detailResult.data, year, month);
        } catch (error) {
            // Error already handled in apiCall
        } finally {
            this.hideLoading();
        }
    }

    renderMonthlyOverview(statusData, dailyData, year, month) {
        const container = document.getElementById('monthly-overview');
        if (!container) return;
        
        const monthNames = ['', 'Leden', 'Únor', 'Březen', 'Duben', 'Květen', 'Červen', 
                           'Červenec', 'Srpen', 'Září', 'Říjen', 'Listopad', 'Prosinec'];
        
        const isAdmin = this.currentUser?.role === 'admin';
        
        // Zpracování dat podle stavů (pro karty)
        let totalRecords = 0;
        let totalAmount = 0;
        let paidAmount = 0;
        let sentAmount = 0;
        
        const statusCounts = {
            zaplaceno: 0,
            zaslano: 0,
            odmitnuto: 0,
            rozpracovane: 0
        };
        
        statusData.forEach(item => {
            totalRecords += parseInt(item.pocet_zaznamu);
            totalAmount += parseFloat(item.celkova_castka || 0);
            
            if (item.stav === 'zaplaceno') {
                statusCounts.zaplaceno = parseInt(item.pocet_zaznamu);
                paidAmount = parseFloat(item.castka_zaplaceno || 0);
            } else if (item.stav === 'zaslano') {
                statusCounts.zaslano = parseInt(item.pocet_zaznamu);
                sentAmount = parseFloat(item.castka_zaslano || 0);
            } else if (item.stav === 'odmitnuto') {
                statusCounts.odmitnuto = parseInt(item.pocet_zaznamu);
            } else if (item.stav === 'rozpracovane') {
                statusCounts.rozpracovane = parseInt(item.pocet_zaznamu);
            }
        });
        
        // Zpracování denních dat pro tabulku
        let dailyTotalRecords = 0;
        let dailyTotalAmount = 0;
        let dailyTotalPaid = 0;
        
        dailyData.forEach(item => {
            dailyTotalRecords += parseInt(item.pocet_zaznamu);
            dailyTotalAmount += parseFloat(item.celkova_castka || 0);
            dailyTotalPaid += parseFloat(item.zaplaceno_castka || 0);
        });
        
        const tableRows = dailyData.map(item => {
            const date = new Date(item.datum);
            const formattedDate = date.toLocaleDateString('cs-CZ');
            const day = date.getDate();
            
            const amountDisplay = isAdmin 
                ? parseFloat(item.celkova_castka || 0).toFixed(2) + ' Kč'
                : '*****';
            const paidDisplay = isAdmin 
                ? parseFloat(item.zaplaceno_castka || 0).toFixed(2) + ' Kč'
                : '*****';
            
            return `
                <tr>
                    <td>${day}.</td>
                    <td>${formattedDate}</td>
                    <td>${item.pocet_zaznamu}</td>
                    <td>${amountDisplay}</td>
                    <td>${paidDisplay}</td>
                </tr>
            `;
        }).join('');
        
        // Karty s částkami pouze pro admin
        const amountCards = isAdmin 
            ? `<div class="overview-card">
                   <h4>Celková částka</h4>
                   <div class="value">${totalAmount.toFixed(2)} Kč</div>
               </div>
               <div class="overview-card">
                   <h4>Zaplaceno</h4>
                   <div class="value">${statusCounts.zaplaceno}</div>
                   <div class="sub-value">${paidAmount.toFixed(2)} Kč</div>
               </div>
               <div class="overview-card">
                   <h4>Zasláno</h4>
                   <div class="value">${statusCounts.zaslano}</div>
                   <div class="sub-value">${sentAmount.toFixed(2)} Kč</div>
               </div>`
            : `<div class="overview-card">
                   <h4>Zaplaceno</h4>
                   <div class="value">${statusCounts.zaplaceno}</div>
               </div>
               <div class="overview-card">
                   <h4>Zasláno</h4>
                   <div class="value">${statusCounts.zaslano}</div>
               </div>`;
        
        container.innerHTML = `
            <h3>Přehled za ${monthNames[parseInt(month)]} ${year}</h3>
            <div class="overview-cards">
                <div class="overview-card">
                    <h4>Celkem záznamů</h4>
                    <div class="value">${totalRecords}</div>
                </div>
                ${amountCards}
                <div class="overview-card">
                    <h4>Odmítnuto</h4>
                    <div class="value">${statusCounts.odmitnuto}</div>
                </div>
                <div class="overview-card">
                    <h4>Rozpracované</h4>
                    <div class="value">${statusCounts.rozpracovane}</div>
                </div>
            </div>
            <div class="monthly-table-container">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Den</th>
                                <th>Datum</th>
                                <th>Počet záznamů</th>
                                <th>Celková částka</th>
                                <th>Zaplaceno</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tableRows}
                            ${dailyData.length > 0 ? `
                            <tr style="font-weight: bold; border-top: 2px solid var(--primary-blue);">
                                <td colspan="2">CELKEM</td>
                                <td>${dailyTotalRecords}</td>
                                <td>${isAdmin ? dailyTotalAmount.toFixed(2) + ' Kč' : '*****'}</td>
                                <td>${isAdmin ? dailyTotalPaid.toFixed(2) + ' Kč' : '*****'}</td>
                            </tr>
                            ` : ''}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    async loadYearlyOverview() {
        try {
            const year = document.getElementById('yearly-year').value;
            
            if (!year) return;
            
            this.showLoading();
            const result = await this.apiCall('yearly-overview', { year });
            this.renderYearlyOverview(result.data, year);
        } catch (error) {
            // Error already handled in apiCall
        } finally {
            this.hideLoading();
        }
    }

    renderYearlyOverview(data, year) {
        const container = document.getElementById('yearly-overview');
        if (!container) return;
        
        const monthNames = ['', 'Leden', 'Únor', 'Březen', 'Duben', 'Květen', 'Červen', 
                           'Červenec', 'Srpen', 'Září', 'Říjen', 'Listopad', 'Prosinec'];
        
        const isAdmin = this.currentUser?.role === 'admin';
        
        let totalRecords = 0;
        let totalAmount = 0;
        let totalPaid = 0;
        
        data.forEach(item => {
            totalRecords += parseInt(item.pocet_zaznamu);
            totalAmount += parseFloat(item.celkova_castka || 0);
            totalPaid += parseFloat(item.zaplaceno_castka || 0);
        });
        
        const tableRows = data.map(item => {
            const amountDisplay = isAdmin 
                ? parseFloat(item.celkova_castka || 0).toFixed(2) + ' Kč'
                : '*****';
            const paidDisplay = isAdmin 
                ? parseFloat(item.zaplaceno_castka || 0).toFixed(2) + ' Kč'
                : '*****';
            
            return `
                <tr>
                    <td>${monthNames[parseInt(item.mesic)]}</td>
                    <td>${item.pocet_zaznamu}</td>
                    <td>${amountDisplay}</td>
                    <td>${paidDisplay}</td>
                </tr>
            `;
        }).join('');
        
        // Karty s částkami pouze pro admin
        const amountCards = isAdmin 
            ? `<div class="overview-card">
                   <h4>Celková částka</h4>
                   <div class="value">${totalAmount.toFixed(2)} Kč</div>
               </div>
               <div class="overview-card">
                   <h4>Zaplaceno celkem</h4>
                   <div class="value">${totalPaid.toFixed(2)} Kč</div>
               </div>`
            : '';
        
        container.innerHTML = `
            <h3>Roční přehled za ${year}</h3>
            <div class="overview-cards">
                <div class="overview-card">
                    <h4>Celkem záznamů</h4>
                    <div class="value">${totalRecords}</div>
                </div>
                ${amountCards}
            </div>
            <div class="yearly-table-container">
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Měsíc</th>
                                <th>Počet záznamů</th>
                                <th>Celková částka</th>
                                <th>Zaplaceno</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tableRows}
                            <tr style="font-weight: bold; border-top: 2px solid var(--primary-blue);">
                                <td>CELKEM</td>
                                <td>${totalRecords}</td>
                                <td>${isAdmin ? totalAmount.toFixed(2) + ' Kč' : '*****'}</td>
                                <td>${isAdmin ? totalPaid.toFixed(2) + ' Kč' : '*****'}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    async exportCSV() {
        try {
            const datumOd = document.getElementById('export-datum-od').value;
            const datumDo = document.getElementById('export-datum-do').value;
            
            let url = 'api.php?action=export-csv';
            if (datumOd) url += `&datum_od=${datumOd}`;
            if (datumDo) url += `&datum_do=${datumDo}`;
            
            window.open(url, '_blank');
            this.showNotification('Informace', 'Export byl spuštěn', 'info');
        } catch (error) {
            this.showNotification('Chyba', 'Chyba při exportu', 'error');
        }
    }

    async importCSV() {
        const fileInput = document.getElementById('import-file');
        const file = fileInput.files[0];
        
        if (!file) {
            this.showNotification('Chyba', 'Vyberte CSV soubor', 'error');
            return;
        }
        
        try {
            this.showLoading();
            
            const formData = new FormData();
            formData.append('csv_file', file);
            
            const response = await fetch('api.php?action=import-csv', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showNotification('Úspěch', `Import dokončen. Importováno ${result.data.imported} záznamů.`, 'success');
                fileInput.value = '';
                if (document.getElementById('records-section').classList.contains('active')) {
                    this.loadRecords();
                }
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            this.showNotification('Chyba', error.message, 'error');
        } finally {
            this.hideLoading();
        }
    }

    async loadUsers() {
        if (this.currentUser?.role !== 'admin') return;
        
        try {
            this.showLoading();
            const result = await this.apiCall('get-users');
            this.renderUsers(result.data);
        } catch (error) {
            // Error already handled in apiCall
        } finally {
            this.hideLoading();
        }
    }

    renderUsers(users) {
        const tbody = document.getElementById('users-tbody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        users.forEach(user => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${user.id}</td>
                <td>${user.username}</td>
                <td><span class="status-badge ${user.role === 'admin' ? 'status-zaplaceno' : 'status-zaslano'}">${user.role}</span></td>
                <td>${new Date(user.created_at).toLocaleDateString('cs-CZ')}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-primary" onclick="app.editUser(${user.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="app.changeUserPassword(${user.id})">
                            <i class="fas fa-key"></i>
                        </button>
                        ${user.id !== 1 ? `<button class="btn btn-sm btn-error" onclick="app.deleteUser(${user.id})">
                            <i class="fas fa-trash"></i>
                        </button>` : ''}
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    showUserModal(userId = null) {
        const modal = document.getElementById('user-modal');
        const title = document.getElementById('user-modal-title');
        const form = document.getElementById('user-form');
        const passwordGroup = document.getElementById('password-group');
        
        if (userId) {
            title.textContent = 'Editovat uživatele';
            passwordGroup.style.display = 'none';
            document.getElementById('user-password').required = false;
            
            // Load user data
            this.apiCall('get-users').then(result => {
                const user = result.data.find(u => u.id == userId);
                if (user) {
                    document.getElementById('user-id').value = user.id;
                    document.getElementById('user-username').value = user.username;
                    document.getElementById('user-role').value = user.role;
                }
            });
        } else {
            title.textContent = 'Přidat uživatele';
            passwordGroup.style.display = 'block';
            document.getElementById('user-password').required = true;
            form.reset();
        }
        
        this.showModal('user-modal');
    }

    editUser(id) {
        this.showUserModal(id);
    }

    async handleUserForm(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        try {
            this.showLoading();
            
            const userId = formData.get('id');
            const username = formData.get('username');
            const password = formData.get('password');
            const role = formData.get('role');
            
            if (userId) {
                // Update user - posílat jen username a role (bez hesla)
                await this.apiCall('update-user', { username, role }, 'POST', { id: userId });
                this.showNotification('Úspěch', 'Uživatel byl aktualizován', 'success');
            } else {
                // Create user - posílat všechna data včetně hesla
                if (!password) {
                    this.showNotification('Chyba', 'Heslo je povinné pro nového uživatele', 'error');
                    return;
                }
                await this.apiCall('create-user', { username, password, role }, 'POST');
                this.showNotification('Úspěch', 'Uživatel byl vytvořen', 'success');
            }
            
            this.closeModal(document.getElementById('user-modal'));
            this.loadUsers();
        } catch (error) {
            // Error already handled in apiCall
        } finally {
            this.hideLoading();
        }
    }

    changeUserPassword(userId) {
        document.getElementById('password-user-id').value = userId;
        document.getElementById('password-form').reset();
        this.showModal('password-modal');
    }

    async handlePasswordForm(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        const newPassword = formData.get('new_password');
        const confirmPassword = formData.get('confirm_password');
        
        if (newPassword !== confirmPassword) {
            this.showNotification('Chyba', 'Hesla se neshodují', 'error');
            return;
        }
        
        try {
            this.showLoading();
            
            const data = {
                new_password: newPassword,
                user_id: formData.get('user_id')
            };
            
            await this.apiCall('change-password', data, 'POST');
            this.showNotification('Úspěch', 'Heslo bylo změněno', 'success');
            this.closeModal(document.getElementById('password-modal'));
        } catch (error) {
            // Error already handled in apiCall
        } finally {
            this.hideLoading();
        }
    }

    async deleteUser(id) {
        if (confirm('Opravdu chcete smazat tohoto uživatele?')) {
            try {
                this.showLoading();
                await this.apiCall('delete-user', null, 'POST', { id: id });
                this.showNotification('Úspěch', 'Uživatel byl smazán', 'success');
                this.loadUsers();
            } catch (error) {
                // Error already handled in apiCall
            } finally {
                this.hideLoading();
            }
        }
    }

    populateYearSelectors() {
        const currentYear = new Date().getFullYear();
        const years = [];
        
        for (let i = currentYear - 2; i <= currentYear + 1; i++) {
            years.push(i);
        }
        
        const yearSelectors = ['monthly-year', 'yearly-year'];
        
        yearSelectors.forEach(selectorId => {
            const selector = document.getElementById(selectorId);
            if (selector) {
                selector.innerHTML = '';
                years.forEach(year => {
                    const option = document.createElement('option');
                    option.value = year;
                    option.textContent = year;
                    if (year === currentYear) {
                        option.selected = true;
                    }
                    selector.appendChild(option);
                });
            }
        });
        
        // Set current month for monthly selector
        const monthSelector = document.getElementById('monthly-month');
        if (monthSelector) {
            monthSelector.value = new Date().getMonth() + 1;
        }
    }

    printTable() {
        window.print();
    }

    showModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
        }
    }

    closeModal(modal) {
        if (modal) {
            modal.classList.remove('active');
        }
    }

    showLoading() {
        document.getElementById('loading-overlay').style.display = 'flex';
    }

    hideLoading() {
        document.getElementById('loading-overlay').style.display = 'none';
    }

    showNotification(title, message, type = 'info') {
        const notifications = document.getElementById('notifications');
        if (!notifications) return;
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        let icon = 'fa-info-circle';
        switch (type) {
            case 'success':
                icon = 'fa-check-circle';
                break;
            case 'error':
                icon = 'fa-exclamation-circle';
                break;
            case 'warning':
                icon = 'fa-exclamation-triangle';
                break;
        }
        
        notification.innerHTML = `
            <i class="fas ${icon}"></i>
            <div class="notification-content">
                <div class="notification-title">${title}</div>
                <div class="notification-message">${message}</div>
            </div>
        `;
        
        notifications.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.remove();
        }, 5000);
        
        // Click to remove
        notification.addEventListener('click', () => {
            notification.remove();
        });
    }

    async viewRecord(id) {
        try {
            this.showLoading();
            
            // Load record data
            const result = await this.apiCall('get-records', { id: id });
            const record = result.data.records.find(r => r.id == id);
            
            if (record) {
                this.renderRecordDetail(record);
                this.showModal('record-detail-modal');
            }
        } catch (error) {
            // Error already handled in apiCall
        } finally {
            this.hideLoading();
        }
    }

    renderRecordDetail(record) {
        const container = document.getElementById('record-detail-content');
        if (!container) return;

        const isAdmin = this.currentUser?.role === 'admin';

        const statusLabels = {
            'zaplaceno': 'Zaplaceno',
            'zaslano': 'Zasláno',
            'odmitnuto': 'Odmítnuto',
            'rozpracovane': 'Rozpracované'
        };

        const formatDate = (dateStr) => {
            if (!dateStr) {
                return 'Neuvedeno';
            }
            
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) {
                console.warn('Invalid date:', dateStr);
                return 'Neplatné datum';
            }
            
            return date.toLocaleString('cs-CZ', {
                day: '2-digit',
                month: '2-digit', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        };

        const formatAmount = (amount) => {
            if (!isAdmin) return '*****';
            return amount ? parseFloat(amount).toFixed(2) + ' Kč' : 'Neuvedeno';
        };

        container.innerHTML = `
            <div class="detail-header">
                <h2>Záznam #${record.id}</h2>
                <div class="status-badge status-${record.stav}">${statusLabels[record.stav] || record.stav}</div>
            </div>
            
            <div class="detail-grid">
                <div class="detail-row">
                    <span class="detail-label">Datum:</span>
                    <span class="detail-value">${formatDate(record.datum)}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Jméno:</span>
                    <span class="detail-value">${record.jmeno}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Účet:</span>
                    <span class="detail-value">${record.ucet || 'Neuvedeno'}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Částka:</span>
                    <span class="detail-value">${formatAmount(record.castka)}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Nick:</span>
                    <span class="detail-value">${record.nick || 'Neuvedeno'}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Faktura:</span>
                    <span class="detail-value">${record.faktura || 'Neuvedeno'}</span>
                </div>
                
                ${record.link ? `
                <div class="detail-row">
                    <span class="detail-label">Link:</span>
                    <span class="detail-value">
                        <a href="${record.link}" target="_blank" class="btn btn-sm btn-secondary">
                            <i class="fas fa-external-link-alt"></i> Otevřít odkaz
                        </a>
                    </span>
                </div>
                ` : ''}
                
                ${record.prani ? `
                <div class="detail-row full-width">
                    <span class="detail-label">Přání:</span>
                    <div class="detail-value detail-text">${record.prani}</div>
                </div>
                ` : ''}
                
                <div class="detail-row">
                    <span class="detail-label">Vytvořeno:</span>
                    <span class="detail-value">${formatDate(record.created_at)}</span>
                </div>
                
                ${record.updated_at !== record.created_at ? `
                <div class="detail-row">
                    <span class="detail-label">Aktualizováno:</span>
                    <span class="detail-value">${formatDate(record.updated_at)}</span>
                </div>
                ` : ''}
            </div>
        `;
    }

    printRecordDetail() {
        const detailContent = document.getElementById('record-detail-content');
        if (!detailContent) return;

        const printWindow = window.open('', '_blank');
        if (!printWindow) return;

        printWindow.document.write(`
            <!DOCTYPE html>
            <html lang="cs">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Detail záznamu - Evidence přání videií</title>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        margin: 20px;
                        color: #333;
                        line-height: 1.6;
                    }
                    
                    .print-header {
                        text-align: center;
                        margin-bottom: 30px;
                        border-bottom: 2px solid #2563eb;
                        padding-bottom: 15px;
                    }
                    
                    .print-header h1 {
                        margin: 0;
                        color: #2563eb;
                        font-size: 24px;
                    }
                    
                    .print-header .subtitle {
                        margin: 5px 0 0 0;
                        color: #666;
                        font-size: 14px;
                    }
                    
                    .detail-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 20px;
                        padding: 15px;
                        background: #f8fafc;
                        border-radius: 8px;
                    }
                    
                    .detail-header h2 {
                        margin: 0;
                        color: #1e40af;
                        font-size: 20px;
                    }
                    
                    .status-badge {
                        padding: 6px 12px;
                        border-radius: 6px;
                        font-weight: 600;
                        font-size: 12px;
                        text-transform: uppercase;
                    }
                    
                    .status-zaplaceno {
                        background: #dcfce7;
                        color: #166534;
                    }
                    
                    .status-zaslano {
                        background: #dbeafe;
                        color: #1e40af;
                    }
                    
                    .status-odmitnuto {
                        background: #fee2e2;
                        color: #dc2626;
                    }
                    
                    .status-rozpracovane {
                        background: #fef3c7;
                        color: #d97706;
                    }
                    
                    .detail-grid {
                        border: 1px solid #e5e7eb;
                        border-radius: 8px;
                        overflow: hidden;
                    }
                    
                    .detail-row {
                        display: flex;
                        border-bottom: 1px solid #e5e7eb;
                        padding: 12px 15px;
                    }
                    
                    .detail-row:last-child {
                        border-bottom: none;
                    }
                    
                    .detail-row.full-width {
                        flex-direction: column;
                    }
                    
                    .detail-label {
                        font-weight: 600;
                        min-width: 120px;
                        color: #374151;
                        margin-bottom: 5px;
                    }
                    
                    .detail-value {
                        flex: 1;
                        color: #111827;
                    }
                    
                    .detail-text {
                        background: #f9fafb;
                        padding: 10px;
                        border-radius: 4px;
                        white-space: pre-wrap;
                        margin-top: 5px;
                    }
                    
                    .print-footer {
                        margin-top: 30px;
                        text-align: center;
                        font-size: 12px;
                        color: #666;
                        border-top: 1px solid #e5e7eb;
                        padding-top: 15px;
                    }
                    
                    @media print {
                        body { margin: 0; }
                        .print-footer { page-break-inside: avoid; }
                    }
                </style>
            </head>
            <body>
                <div class="print-header">
                    <h1>Evidence přání videií v3.2.0</h1>
                    <p class="subtitle">Detail záznamu</p>
                </div>
                
                ${detailContent.innerHTML}
                
                <div class="print-footer">
                    <p>Vytištěno: ${new Date().toLocaleString('cs-CZ')}</p>
                </div>
            </body>
            </html>
        `);

        printWindow.document.close();
        printWindow.focus();
        
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    }
}

// Funkce pro otevření audit logů
function openAuditLogs() {
    window.open('audit_logs.html', '_blank');
}

// Initialize app
const app = new EvidenceApp();