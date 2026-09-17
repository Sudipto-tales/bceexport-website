<?php

/**
 * The Doctor form's markup.
 *
 * The one screen whose fields are written out by hand rather than generated
 * by core/fields.js, and deliberately so: it is the readable reference for
 * what the generated markup corresponds to. scaffold.mjs excluded it for
 * that reason — regenerating the file would have destroyed it. Here the
 * markup has a component of its own and the shell above it is as ordinary
 * as any other, so it can be scaffolded like the rest.
 *
 * Copied from the prototype's doctor-form screen unchanged.
 */
?>
                <div id="pageHead"></div>

                <div class="split">
                    <form class="card" id="doctorForm" novalidate>

                        <!-- ============ IDENTITY ============ -->
                        <section class="form-section">
                            <div class="form-section__head">
                                <h3><i class="fa-solid fa-id-card" style="color:var(--brand-red)"></i> Identity</h3>
                                <p>What appears on the doctor card, the department team strip and the article byline.</p>
                            </div>

                            <div class="form-grid">
                                <div class="field">
                                    <label for="f-name">Full name <span class="field__req">*</span></label>
                                    <input type="text" id="f-name" name="name" required placeholder="Dr. Anita Sharma">
                                </div>

                                <div class="field">
                                    <label for="f-id">URL slug <span class="field__req">*</span></label>
                                    <input type="text" id="f-id" name="id" required data-rule="slug" placeholder="dr-anita-sharma">
                                    <small>Used in the page address. Changing it on a published doctor breaks existing links.</small>
                                </div>

                                <div class="field">
                                    <label for="f-role">Role <span class="field__req">*</span></label>
                                    <input type="text" id="f-role" name="role" required placeholder="Interventional Cardiologist">
                                </div>

                                <div class="field">
                                    <label for="f-qual">Qualification <span class="field__req">*</span></label>
                                    <input type="text" id="f-qual" name="qualification" required placeholder="MD, DNB (Cardiology)">
                                </div>

                                <div class="field">
                                    <label for="f-exp">Years of experience</label>
                                    <input type="number" id="f-exp" name="experienceYears" min="0" data-min="0" placeholder="14">
                                </div>

                                <div class="field">
                                    <label for="f-reg">Registration number</label>
                                    <input type="text" id="f-reg" name="registrationNo" placeholder="WBMC-52901">
                                </div>

                                <div class="field field--wide">
                                    <label>Portrait <span class="field__req">*</span></label>
                                    <div class="media-pick" data-media="photo"></div>
                                    <input type="hidden" name="photo" required data-required-message="A portrait is required">
                                    <small>Square crop, at least 500&times;500. Portraits without alt text are flagged in the gallery.</small>
                                </div>
                            </div>
                        </section>

                        <div class="divider"></div>

                        <!-- ============ ASSIGNMENT ============ -->
                        <section class="form-section">
                            <div class="form-section__head">
                                <h3><i class="fa-solid fa-hospital" style="color:var(--brand-red)"></i> Assignment</h3>
                                <p>Which department pages list this doctor in their team strip.</p>
                            </div>

                            <div class="form-grid">
                                <div class="field">
                                    <label>Departments</label>
                                    <!-- core/multiselect.js builds the control inside this host and
                                         keeps the hidden input in step; data-options is filled by
                                         doctor-form.js once the departments have loaded. -->
                                    <div class="multiselect" id="f-depts" data-multiselect="departments"
                                         data-options="[]" data-placeholder="Choose departments"
                                         data-search-placeholder="Search departments">
                                        <input type="hidden" name="departments">
                                    </div>
                                    <small>Pick as many as apply. Each one shows as a tag you can remove.</small>
                                </div>

                                <div class="col gap-4">
                                    <div class="field">
                                        <label for="f-spec">Speciality</label>
                                        <input type="text" id="f-spec" name="speciality" placeholder="Angioplasty and structural heart">
                                    </div>
                                    <div class="field">
                                        <label for="f-lang">Languages</label>
                                        <input type="text" id="f-lang" name="languages" placeholder="Bangla, English, Hindi">
                                    </div>
                                    <div class="field">
                                        <label for="f-fee">Consultation fee (&#8377;)</label>
                                        <input type="number" id="f-fee" name="consultationFee" min="0" data-min="0" placeholder="700">
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="divider"></div>

                        <!-- ============ PROFILE ============ -->
                        <section class="form-section">
                            <div class="form-section__head">
                                <h3><i class="fa-solid fa-file-lines" style="color:var(--brand-red)"></i> Profile</h3>
                                <p>The biography and clinic times shown on the doctor’s own page.</p>
                            </div>

                            <div class="field field--wide mb-4">
                                <label for="f-bio">Biography</label>
                                <textarea id="f-bio" name="bio" rows="5"
                                    placeholder="Two or three sentences. Where they trained, what they lead, what they are known for."></textarea>
                                <small>Plain paragraphs. The full rich-text editor lives on blog posts.</small>
                            </div>

                            <div class="field field--wide">
                                <label>Clinic schedule</label>
                                <div class="repeater" data-repeater="schedule" data-cols="4" data-add-label="Add a clinic slot"
                                     data-fields='[
                                        {"key":"day","type":"select","label":"Day","options":["Mon","Tue","Wed","Thu","Fri","Sat","Sun"]},
                                        {"key":"from","type":"time","label":"From"},
                                        {"key":"to","type":"time","label":"To"},
                                        {"key":"location","placeholder":"OPD 2","label":"Location"}]'></div>
                            </div>
                        </section>

                        <div class="divider"></div>

                        <!-- ============ VISIBILITY ============ -->
                        <section class="form-section">
                            <div class="form-section__head">
                                <h3><i class="fa-solid fa-eye" style="color:var(--brand-red)"></i> Visibility</h3>
                            </div>

                            <div class="form-grid">
                                <div class="field">
                                    <label for="f-status">Status</label>
                                    <select id="f-status" name="status">
                                        <option value="draft">Draft — not on the site</option>
                                        <option value="published">Published — live</option>
                                        <option value="hidden">Hidden — kept, but not shown</option>
                                    </select>
                                </div>

                                <div class="field">
                                    <label for="f-order">Display order</label>
                                    <input type="number" id="f-order" name="order" min="1" data-min="1">
                                    <small>Lower numbers come first on the doctors page.</small>
                                </div>

                                <div class="field field--wide">
                                    <label class="toggle">
                                        <input type="checkbox" name="isLeadership">
                                        <span class="toggle__track"></span>
                                        <span class="toggle__text">Also show on the About page leadership strip</span>
                                    </label>
                                </div>

                                <div class="field field--wide">
                                    <label class="toggle">
                                        <input type="checkbox" name="appointmentEnabled" checked>
                                        <span class="toggle__track"></span>
                                        <span class="toggle__text">Appointments available</span>
                                    </label>
                                    <small>On: the doctor card shows a “Book an appointment” link to the contact
                                        page with this doctor preselected. Off: no link at all. The site never
                                        takes a booking itself &mdash; the desk calls back.</small>
                                </div>
                            </div>
                        </section>

                        <div class="divider"></div>

                        <!-- ============ SEO ============ -->
                        <section class="form-section">
                            <div class="form-section__head">
                                <h3><i class="fa-solid fa-magnifying-glass-chart" style="color:var(--brand-red)"></i> Search appearance</h3>
                                <p>How this page reads in a Google result. Left empty, the name and role are used.</p>
                            </div>

                            <div class="form-grid">
                                <div class="field field--wide">
                                    <label for="f-mt">Meta title</label>
                                    <input type="text" id="f-mt" name="metaTitle" data-max="60"
                                        data-required-to-publish placeholder="Dr. Anita Sharma — Interventional Cardiologist">
                                </div>
                                <div class="field field--wide">
                                    <label for="f-md">Meta description</label>
                                    <textarea id="f-md" name="metaDescription" data-max="155" rows="3"></textarea>
                                </div>
                            </div>
                        </section>

                        <!-- ============ ACTION BAR ============ -->
                        <div class="form-bar" id="formBar">
                            <div class="form-bar__status"></div>
                            <button type="button" class="btn btn--ghost" data-cancel>Cancel</button>
                            <button type="button" class="btn btn--soft" data-save-draft>Save draft</button>
                            <button type="button" class="btn btn--primary" data-publish>
                                <i class="fa-solid fa-cloud-arrow-up"></i> <span id="publishLabel">Publish</span></button>
                        </div>
                    </form>

                    <!-- ============ RAIL ============ -->
                    <aside class="split__rail">
                        <article class="card" id="previewCard">
                            <div class="card__head"><h3>Live preview</h3></div>
                            <div id="preview"></div>
                            <p class="text-xs muted mt-4">This is how the card renders on a department team strip.</p>
                        </article>

                        <article class="card card--quiet" id="metaCard"></article>
                    </aside>
                </div>
