<div <?php echo get_block_wrapper_attributes(); ?>>
	<div class="ugafooter alignfull">
		<div class="ugafooter__container">
			<div class="ugafooter__row ugafooter__row--primary">
				<div class="ugafooter__logo">
					<a class="ugafooter__logo-link" href="https://www.uga.edu/">University of Georgia</a>
				</div>
				<nav class="ugafooter__links">
					<ul class="ugafooter__links-list">
						<li class="ugafooter__links-list-item">
							<a class="ugafooter__links-list-link" href="https://www.uga.edu/a-z/schools/">Schools and Colleges</a>
						</li>
						<li class="ugafooter__links-list-item">
							<a class="ugafooter__links-list-link" href="https://peoplesearch.uga.edu/">Directory</a>
						</li>
						<li class="ugafooter__links-list-item">
							<a class="ugafooter__links-list-link" href="https://my.uga.edu/">MyUGA</a>
						</li>
						<li class="ugafooter__links-list-item">
							<a class="ugafooter__links-list-link" href="http://hr.uga.edu/applicants/">Employment Opportunities</a>
						</li>
						<li class="ugafooter__links-list-item">
							<a class="ugafooter__links-list-link" href="https://mc.uga.edu/policy/trademark">Copyright and Trademarks</a>
						</li>
						<li class="ugafooter__links-list-item">
							<a class="ugafooter__links-list-link" href="https://eits.uga.edu/access_and_security/infosec/pols_regs/policies/privacy/">UGA Privacy Policy</a>
						</li>
						<?php
						$submitComplaintLink = $attributes['submitComplaintLink'];
						switch ($submitComplaintLink) {
							case "true": ?>
								<li class="ugafooter__links-list-item">
									<a class="ugafooter__links-list-link" href="https://studentcomplaints.uga.edu/">Submit a Student Complaint</a>
								</li>
								<?php break; ?>
						<?php } ?>
						<?php
						$login = $attributes['login'];
						switch ($login) {
							case "true": ?>
								<?php if (is_user_logged_in()) : ?>
									<li class="ugafooter__links-list-item"><a class="ugafooter__links-list-link" href="<?php echo wp_logout_url(get_permalink()); ?>">Log out of site</a></li>
								<?php else : ?>
									<li class="ugafooter__links-list-item"><a class="ugafooter__links-list-link" href="<?php echo wp_login_url(get_permalink()); ?>">Log in to site</a></li>
								<?php endif; ?>
							<?php break;
							case "false": ?>
								<?php break; ?>
						<?php } ?>
					</ul>
				</nav>
			</div>
			<div class="ugafooter__row ugafooter__row--secondary">
				<nav class="ugafooter__social" aria-label="U.G.A. Social Media">
					<span class="ugafooter__social-label">#UGA on</span>
					<a class="ugafooter__social-link" aria-label="UGA on Facebook" href="https://www.facebook.com/universityofga/">
						<svg viewBox="0 0 264 512">
							<path d="M76.7 512V283H0v-91h76.7v-71.7C76.7 42.4 124.3 0 193.8 0c33.3 0 61.9 2.5 70.2 3.6V85h-48.2c-37.8 0-45.1 18-45.1 44.3V192H256l-11.7 91h-73.6v229"></path>
						</svg>
					</a>
					<a class="ugafooter__social-link" aria-label="UGA on X" href="https://twitter.com/universityofga">
						<svg viewBox="0 0 512 512">
							<path d="M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z"></path>
						</svg>
					</a>
					<a class="ugafooter__social-link" aria-label="UGA on Instagram" href="https://www.instagram.com/universityofga/">
						<svg viewBox="0 0 448 512">
							<path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z"></path>
						</svg>
					</a>
					<!-- <a class="ugafooter__social-link" aria-label="UGA on Snapchat" href="https://www.snapchat.com/add/university-ga">
						<svg viewBox="0 0 512 512">
							<path d="M510.846 392.673c-5.211 12.157-27.239 21.089-67.36 27.318-2.064 2.786-3.775 14.686-6.507 23.956-1.625 5.566-5.623 8.869-12.128 8.869l-.297-.005c-9.395 0-19.203-4.323-38.852-4.323-26.521 0-35.662 6.043-56.254 20.588-21.832 15.438-42.771 28.764-74.027 27.399-31.646 2.334-58.025-16.908-72.871-27.404-20.714-14.643-29.828-20.582-56.241-20.582-18.864 0-30.736 4.72-38.852 4.72-8.073 0-11.213-4.922-12.422-9.04-2.703-9.189-4.404-21.263-6.523-24.13-20.679-3.209-67.31-11.344-68.498-32.15a10.627 10.627 0 0 1 8.877-11.069c69.583-11.455 100.924-82.901 102.227-85.934.074-.176.155-.344.237-.515 3.713-7.537 4.544-13.849 2.463-18.753-5.05-11.896-26.872-16.164-36.053-19.796-23.715-9.366-27.015-20.128-25.612-27.504 2.437-12.836 21.725-20.735 33.002-15.453 8.919 4.181 16.843 6.297 23.547 6.297 5.022 0 8.212-1.204 9.96-2.171-2.043-35.936-7.101-87.29 5.687-115.969C158.122 21.304 229.705 15.42 250.826 15.42c.944 0 9.141-.089 10.11-.089 52.148 0 102.254 26.78 126.723 81.643 12.777 28.65 7.749 79.792 5.695 116.009 1.582.872 4.357 1.942 8.599 2.139 6.397-.286 13.815-2.389 22.069-6.257 6.085-2.846 14.406-2.461 20.48.058l.029.01c9.476 3.385 15.439 10.215 15.589 17.87.184 9.747-8.522 18.165-25.878 25.018-2.118.835-4.694 1.655-7.434 2.525-9.797 3.106-24.6 7.805-28.616 17.271-2.079 4.904-1.256 11.211 2.46 18.748.087.168.166.342.239.515 1.301 3.03 32.615 74.46 102.23 85.934 6.427 1.058 11.163 7.877 7.725 15.859z"></path>
						</svg>
					</a> -->
					<a class="ugafooter__social-link" aria-label="UGA on YouTube" href="https://www.youtube.com/user/UniversityOfGeorgia">
						<svg viewBox="0 0 576 512">
							<path d="M549.655 124.083c-6.281-23.65-24.787-42.276-48.284-48.597C458.781 64 288 64 288 64S117.22 64 74.629 75.486c-23.497 6.322-42.003 24.947-48.284 48.597-11.412 42.867-11.412 132.305-11.412 132.305s0 89.438 11.412 132.305c6.281 23.65 24.787 41.5 48.284 47.821C117.22 448 288 448 288 448s170.78 0 213.371-11.486c23.497-6.321 42.003-24.171 48.284-47.821 11.412-42.867 11.412-132.305 11.412-132.305s0-89.438-11.412-132.305zm-317.51 213.508V175.185l142.739 81.205-142.739 81.201z"></path>
						</svg>
					</a>
					<a class="ugafooter__social-link" aria-label="UGA on LinkedIn" href="https://www.linkedin.com/school/university-of-georgia/">
						<svg viewBox="0 0 448 512">
							<path d="M416 32H31.9C14.3 32 0 46.5 0 64.3v383.4C0 465.5 14.3 480 31.9 480H416c17.6 0 32-14.5 32-32.3V64.3c0-17.8-14.4-32.3-32-32.3zM135.4 416H69V202.2h66.5V416zm-33.2-243c-21.3 0-38.5-17.3-38.5-38.5S80.9 96 102.2 96c21.2 0 38.5 17.3 38.5 38.5 0 21.3-17.2 38.5-38.5 38.5zm282.1 243h-66.4V312c0-24.8-.5-56.7-34.5-56.7-34.6 0-39.9 27-39.9 54.9V416h-66.4V202.2h63.7v29.2h.9c8.9-16.8 30.6-34.5 62.9-34.5 67.2 0 79.7 44.3 79.7 101.9V416z"></path>
						</svg>
					</a>
				</nav>
				<div class="ugafooter__address">
					&copy; University of Georgia, Athens, GA 30602<br>
					706&#8209;542&#8209;3000
				</div>
			</div>
		</div>
		</footer>
	</div>