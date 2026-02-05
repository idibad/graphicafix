
<div class="modal fade" id="projectRequestModal" tabindex="-1" aria-labelledby="projectRequestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="projectRequestModalLabel">Request a Project</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="projectRequestForm">
                    <div class="row">
                        <!-- Name -->
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="Enter your name" required>
                        </div>
                        <!-- Company -->
                        <div class="col-md-6">
                            <label for="company" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company" name="company" placeholder="Your company">
                        </div>
                    </div>

                    <div class="row mt-3">
                        <!-- Email -->
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Your email" required>
                        </div>
                        <!-- Phone -->
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" placeholder="Your phone number" required>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <!-- Project Type -->
                        <div class="col-md-6">
                            <label for="projectType" class="form-label">Project Type</label>
                            <select class="form-select" id="projectType" name="projectType" required>
                                <option value="" selected disabled>Select project type</option>
                                <option value="Website Design">Website Design</option>
                                <option value="Mobile App">Mobile App</option>
                                <option value="Graphic Design">Graphic Design</option>
                                <option value="Marketing Campaign">Marketing Campaign</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <!-- Estimated Budget -->
                        <div class="col-md-6">
                            <label for="budget" class="form-label">Estimated Budget (PKR)</label>
                            <input type="number" class="form-control" id="budget" name="budget" placeholder="Enter estimated budget">
                        </div>
                    </div>

                    <div class="row mt-3">
                        <!-- Estimated Time -->
                        <div class="col-md-6">
                            <label for="time" class="form-label">Estimated Timeframe</label>
                            <input type="text" class="form-control" id="time" name="time" placeholder="E.g. 2 weeks, 3 months" required>
                        </div>
                        <!-- File Upload -->
                        <div class="col-md-6">
                            <label for="attachment" class="form-label">Attach File (Optional)</label>
                            <input type="file" class="form-control" id="attachment" name="attachment">
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mt-3">
                        <label for="description" class="form-label">Project Description</label>
                        <textarea class="form-control" id="description" name="description" rows="4" placeholder="Describe your project in detail..." required></textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="mt-4">
                        <button type="submit" class="btn main-btn">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>