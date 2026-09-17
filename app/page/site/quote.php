<?php
// Quote page template
?>
<!-- Quote Start -->
<div class="container-xxl py-5">
    <div class="container py-5">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5 wow fadeInUp" data-wow-delay="0.1s">
                <h6 class="text-secondary text-uppercase mb-3">Get A Quote</h6>
                <h1 class="mb-5">Request A Free Quote!</h1>
                <p class="mb-5">Looking for reliable import and export services? Request a free quote today! Let us handle your global trade needs with expertise and efficiency.</p>
                <div class="d-flex align-items-center">
                    <i class="fa fa-phone fa-2x flex-shrink-0 bg-primary p-3 text-white"></i>
                    <div class="ps-4">
                        <h6>whatsapp for any query!</h6>
                        <a href="https://wa.me/<?= isset($settings['whatsapp']) ? e($settings['whatsapp']) : '+918900379037' ?>">
                            <h3 class="text-primary m-0"><?= isset($settings['whatsapp']) ? e($settings['whatsapp']) : '+91 8900379037' ?></h3>
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="bg-light text-center p-5 wow fadeIn" data-wow-delay="0.5s">
                    <form id="quoteForm" class="quote-form">
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <input type="text" id="quoteName" name="name" class="form-control border-0" placeholder="Your Name" style="height: 55px;" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <input type="email" id="quoteEmail" name="email" class="form-control border-0" placeholder="Your Email" style="height: 55px;" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <input type="text" id="quoteMobile" name="mobile" class="form-control border-0" placeholder="Your Mobile" style="height: 55px;" required>
                            </div>
                            <div class="col-12 col-sm-6">
                                <select name="country" class="form-select border-0" style="height: 55px;">
                                    <option selected>Select your country</option>
                                    <option value="1">Afghanistan</option>
                                    <option value="2">Albania</option>
                                    <option value="3">Algeria</option>
                                    <option value="4">Andorra</option>
                                    <option value="5">Angola</option>
                                    <option value="6">Antigua</option>
                                    <option value="7">Argentina</option>
                                    <option value="8">Armenia</option>
                                    <option value="9">Australia</option>
                                    <option value="10">Austria</option>
                                    <option value="11">Azerbaijan</option>
                                    <option value="12">Bahamas</option>
                                    <option value="13">Bahrain</option>
                                    <option value="14">Bangladesh</option>
                                    <option value="15">Barbados</option>
                                    <option value="16">Belarus</option>
                                    <option value="17">Belgium</option>
                                    <option value="18">Belize</option>
                                    <option value="19">Benin</option>
                                    <option value="20">Bhutan</option>
                                    <option value="21">Bolivia</option>
                                    <option value="22">Bosnia and Herzegovina</option>
                                    <option value="23">Brazil</option>
                                    <option value="24">Brunei</option>
                                    <option value="25">Bulgaria</option>
                                    <option value="26">Burkina Faso</option>
                                    <option value="27">Burundi</option>
                                    <option value="28">Cabo Verde</option>
                                    <option value="29">Cambodia</option>
                                    <option value="30">Cameroon</option>
                                    <option value="31">Canada</option>
                                    <option value="32">Central African Republic</option>
                                    <option value="33">Chad</option>
                                    <option value="34">Chile</option>
                                    <option value="35">China</option>
                                    <option value="36">Colombia</option>
                                    <option value="37">Comoros</option>
                                    <option value="38">Congo</option>
                                    <option value="39">Costa Rica</option>
                                    <option value="40">Croatia</option>
                                    <option value="41">Cuba</option>
                                    <option value="42">Cyprus</option>
                                    <option value="43">Czech Republic</option>
                                    <option value="44">Denmark</option>
                                    <option value="45">Djibouti</option>
                                    <option value="46">Dominica</option>
                                    <option value="47">Dominican Republic</option>
                                    <option value="48">East Timor</option>
                                    <option value="49">Ecuador</option>
                                    <option value="50">Egypt</option>
                                    <option value="51">El Salvador</option>
                                    <option value="52">Equatorial Guinea</option>
                                    <option value="53">Eritrea</option>
                                    <option value="54">Estonia</option>
                                    <option value="55">Eswatini</option>
                                    <option value="56">Ethiopia</option>
                                    <option value="57">Fiji</option>
                                    <option value="58">Finland</option>
                                    <option value="59">France</option>
                                    <option value="60">Gabon</option>
                                    <option value="61">Gambia</option>
                                    <option value="62">Georgia</option>
                                    <option value="63">Germany</option>
                                    <option value="64">Ghana</option>
                                    <option value="65">Greece</option>
                                    <option value="66">Grenada</option>
                                    <option value="67">Guatemala</option>
                                    <option value="68">Guinea</option>
                                    <option value="69">Guinea-Bissau</option>
                                    <option value="70">Guyana</option>
                                    <option value="71">Haiti</option>
                                    <option value="72">Honduras</option>
                                    <option value="73">Hungary</option>
                                    <option value="74">Iceland</option>
                                    <option value="75">India</option>
                                    <option value="76">Indonesia</option>
                                    <option value="77">Iran</option>
                                    <option value="78">Iraq</option>
                                    <option value="79">Ireland</option>
                                    <option value="80">Israel</option>
                                    <option value="81">Italy</option>
                                    <option value="82">Ivory Coast</option>
                                    <option value="83">Jamaica</option>
                                    <option value="84">Japan</option>
                                    <option value="85">Jordan</option>
                                    <option value="86">Kazakhstan</option>
                                    <option value="87">Kenya</option>
                                    <option value="88">Kiribati</option>
                                    <option value="89">Korea (North)</option>
                                    <option value="90">Korea (South)</option>
                                    <option value="91">Kosovo</option>
                                    <option value="92">Kuwait</option>
                                    <option value="93">Kyrgyzstan</option>
                                    <option value="94">Laos</option>
                                    <option value="95">Latvia</option>
                                    <option value="96">Lebanon</option>
                                    <option value="97">Lesotho</option>
                                    <option value="98">Liberia</option>
                                    <option value="99">Libya</option>
                                    <option value="100">Liechtenstein</option>
                                    <option value="101">Lithuania</option>
                                    <option value="102">Luxembourg</option>
                                    <option value="103">Madagascar</option>
                                    <option value="104">Malawi</option>
                                    <option value="105">Malaysia</option> 
                                    <option value="106">Maldives</option>
                                    <option value="107">Mali</option>
                                    <option value="108">Malta</option>
                                    <option value="109">Marshall Islands</option>
                                    <option value="110">Mauritania</option>
                                    <option value="111">Mauritius</option>
                                    <option value="112">Mexico</option>
                                    <option value="113">Micronesia</option>
                                    <option value="114">Moldova</option>
                                    <option value="115">Monaco</option>
                                    <option value="116">Mongolia</option>
                                    <option value="117">Montenegro</option>
                                    <option value="118">Morocco</option>
                                    <option value="119">Mozambique</option>
                                    <option value="120">Myanmar</option>
                                    <option value="121">Namibia</option>
                                    <option value="122">Nauru</option>
                                    <option value="123">Nepal</option>
                                    <option value="124">Netherlands</option>
                                    <option value="125">New Zealand</option>
                                    <option value="126">Nicaragua</option>
                                    <option value="127">Niger</option>
                                    <option value="128">Nigeria</option>
                                    <option value="129">North Macedonia</option>
                                    <option value="130">Norway</option>
                                    <option value="131">Oman</option>
                                    <option value="132">Pakistan</option>
                                    <option value="133">Palau</option>
                                    <option value="134">Panama</option>
                                    <option value="135">Papua New Guinea</option>
                                    <option value="136">Paraguay</option>
                                    <option value="137">Peru</option>
                                    <option value="138">Philippines</option>
                                    <option value="139">Poland</option>
                                    <option value="140">Portugal</option>
                                    <option value="141">Qatar</option>
                                    <option value="142">Romania</option>
                                    <option value="143">Russia</option>
                                    <option value="144">Rwanda</option>
                                    <option value="145">Saint Kitts and Nevis</option>
                                    <option value="146">Saint Lucia</option>
                                    <option value="147">Saint Vincent and the Grenadines</option>
                                    <option value="148">Saudi Arabia</option>
                                    <option value="149">Senegal</option>
                                    <option value="150">Serbia</option>
                                    <option value="151">Seychelles</option>
                                    <option value="152">Sierra Leone</option>
                                    <option value="153">Singapore</option>
                                    <option value="154">Slovakia</option>
                                    <option value="155">Slovenia</option>
                                    <option value="156">Solomon Islands</option>
                                    <option value="157">Somalia</option>
                                    <option value="158">South Africa</option>
                                    <option value="159">South Korea</option>
                                    <option value="160">South Sudan</option>
                                    <option value="161">Spain</option>
                                    <option value="162">Sri Lanka</option>
                                    <option value="163">Sudan</option>
                                    <option value="164">Suriname</option>
                                    <option value="165">Sweden</option>
                                    <option value="166">Switzerland</option>
                                    <option value="167">Syria</option>
                                    <option value="168">Taiwan</option>
                                    <option value="169">Tajikistan</option>
                                    <option value="170">Tanzania</option>
                                    <option value="171">Thailand</option>
                                    <option value="172">Togo</option>
                                    <option value="173">Tonga</option>
                                    <option value="174">Trinidad and Tobago</option>
                                    <option value="175">Tunisia</option>
                                    <option value="176">Turkey</option>
                                    <option value="177">Turkmenistan</option>
                                    <option value="178">Tuvalu</option>
                                    <option value="179">Uganda</option>
                                    <option value="180">Ukraine</option>
                                    <option value="181">United Arab Emirates</option>
                                    <option value="182">United Kingdom</option>
                                    <option value="183">United States of America</option>
                                    <option value="184">Uruguay</option>
                                    <option value="185">Uzbekistan</option>
                                    <option value="186">Vanuatu</option>
                                    <option value="187">Vatican City</option>
                                    <option value="188">Venezuela</option>
                                    <option value="189">Vietnam</option>
                                    <option value="190">Yemen</option>
                                    <option value="191">Zambia</option>
                                    <option value="192">Zimbabwe</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <textarea class="form-control border-0" id="quoteMessage" name="message" placeholder="Your Message" rows="4" required></textarea>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary w-100 py-3" type="submit">Send Message</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Quote End -->
