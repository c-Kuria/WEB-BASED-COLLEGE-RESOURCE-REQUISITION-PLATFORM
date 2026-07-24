<div class="card password-card">

    <div class="section-header">
        <h2>Change Password</h2>

        <p>
            Enter your current password before choosing
            a new password.
        </p>
    </div>

    <form method="POST">

        <input
            type="hidden"
            name="action"
            value="change_password"
        >

        <div class="form-grid">

            <div class="form-group full-width">

                <label for="currentPassword">
                    Current Password
                </label>

                <div class="password-input-wrapper">

                    <input
                        type="password"
                        id="currentPassword"
                        name="currentPassword"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        data-target="currentPassword"
                    >
                        Show
                    </button>

                </div>

            </div>

            <div class="form-group">

                <label for="newPassword">
                    New Password
                </label>

                <div class="password-input-wrapper">

                    <input
                        type="password"
                        id="newPassword"
                        name="newPassword"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        data-target="newPassword"
                    >
                        Show
                    </button>

                </div>

            </div>

            <div class="form-group">

                <label for="confirmPassword">
                    Confirm New Password
                </label>

                <div class="password-input-wrapper">

                    <input
                        type="password"
                        id="confirmPassword"
                        name="confirmPassword"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        class="password-toggle"
                        data-target="confirmPassword"
                    >
                        Show
                    </button>

                </div>

            </div>

        </div>

        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                Change Password
            </button>

        </div>

    </form>

</div>