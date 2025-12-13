    </main>

    <!-- Footer -->
    <footer class="page-footer blue-grey darken-4">
        <div class="container">
            <div class="row">
                <div class="col l6 s12">
                    <h5 class="white-text">PHOENIX Adjudication System</h5>
                    <p class="grey-text text-lighten-4">
                        Pharmacogenomic Trial Outcome Adjudication Platform
                    </p>
                    <p class="grey-text text-lighten-4">
                        <i class="material-icons tiny">info</i>
                        Version <?= htmlspecialchars($config['version'] ?? '1.0.0') ?>
                    </p>
                </div>
                <div class="col l3 s12">
                    <h5 class="white-text">Resources</h5>
                    <ul>
                        <li><a class="grey-text text-lighten-3" href="/docs/user-guide.php">User Guide</a></li>
                        <li><a class="grey-text text-lighten-3" href="/docs/api.php">API Documentation</a></li>
                        <li><a class="grey-text text-lighten-3" href="/docs/changelog.php">Changelog</a></li>
                    </ul>
                </div>
                <div class="col l3 s12">
                    <h5 class="white-text">Support</h5>
                    <ul>
                        <li><a class="grey-text text-lighten-3" href="mailto:support@phoenix-trial.org">
                            <i class="material-icons tiny">email</i> Contact Support
                        </a></li>
                        <li><a class="grey-text text-lighten-3" href="/docs/faq.php">
                            <i class="material-icons tiny">help</i> FAQ
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-copyright">
            <div class="container">
                © <?= date('Y') ?> PHOENIX Trial
                <span class="grey-text text-lighten-4 right hide-on-small-only">
                    <?php if ($user): ?>
                        Logged in as <?= htmlspecialchars($user['email']) ?> (<?= htmlspecialchars($user['role']) ?>)
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </footer>

    <!-- Materialize JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>

    <!-- Initialize Materialize components -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize sidenav
            var sidenavElems = document.querySelectorAll('.sidenav');
            M.Sidenav.init(sidenavElems, {
                edge: 'left',
                draggable: true
            });

            // Initialize dropdowns
            var dropdownElems = document.querySelectorAll('.dropdown-trigger');
            M.Dropdown.init(dropdownElems, {
                coverTrigger: false,
                constrainWidth: false,
                alignment: 'right'
            });

            // Initialize modals
            var modalElems = document.querySelectorAll('.modal');
            M.Modal.init(modalElems);

            // Initialize select dropdowns
            var selectElems = document.querySelectorAll('select');
            M.FormSelect.init(selectElems);

            // Initialize tooltips
            var tooltipElems = document.querySelectorAll('.tooltipped');
            M.Tooltip.init(tooltipElems);

            // Initialize collapsibles
            var collapsibleElems = document.querySelectorAll('.collapsible');
            M.Collapsible.init(collapsibleElems);

            // Initialize tabs
            var tabElems = document.querySelectorAll('.tabs');
            M.Tabs.init(tabElems);

            // Initialize chips
            var chipElems = document.querySelectorAll('.chips');
            M.Chips.init(chipElems);

            // Initialize datepicker
            var datepickerElems = document.querySelectorAll('.datepicker');
            M.Datepicker.init(datepickerElems, {
                format: 'yyyy-mm-dd',
                autoClose: true,
                firstDay: 1
            });

            // Initialize floating action button
            var fabElems = document.querySelectorAll('.fixed-action-btn');
            M.FloatingActionButton.init(fabElems);

            // Initialize materialbox (lightbox for images)
            var materialboxElems = document.querySelectorAll('.materialboxed');
            M.Materialbox.init(materialboxElems);

            // Auto-update labels for pre-filled inputs
            M.updateTextFields();
        });

        /**
         * Show toast notification
         * @param {string} message - The message to display
         * @param {string} type - Type of toast: success, error, info, warning
         */
        function showToast(message, type = 'info') {
            const classes = {
                success: 'green darken-1',
                error: 'red darken-1',
                warning: 'orange darken-1',
                info: 'blue darken-1'
            };

            M.toast({
                html: message,
                classes: classes[type] || classes.info,
                displayLength: 4000
            });
        }

        /**
         * Show loading overlay
         * @param {string} message - Optional loading message
         */
        function showLoading(message = 'Loading...') {
            let overlay = document.getElementById('loading-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'loading-overlay';
                overlay.className = 'loading-overlay';
                overlay.innerHTML = `
                    <div class="preloader-wrapper big active">
                        <div class="spinner-layer spinner-blue-only">
                            <div class="circle-clipper left">
                                <div class="circle"></div>
                            </div>
                            <div class="gap-patch">
                                <div class="circle"></div>
                            </div>
                            <div class="circle-clipper right">
                                <div class="circle"></div>
                            </div>
                        </div>
                    </div>
                    <p class="white-text" style="margin-top: 20px;">${message}</p>
                `;
                document.body.appendChild(overlay);
            } else {
                overlay.querySelector('p').textContent = message;
                overlay.style.display = 'flex';
            }
        }

        /**
         * Hide loading overlay
         */
        function hideLoading() {
            const overlay = document.getElementById('loading-overlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }
    </script>

    <!-- Custom API utilities -->
    <script src="/assets/js/api.js"></script>

    <!-- Page-specific JavaScript -->
    <?php if (isset($customJS)): ?>
        <?php foreach ((array)$customJS as $js): ?>
            <script src="<?= htmlspecialchars($js) ?>?v=<?= $config['version'] ?? '1.0.0' ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
