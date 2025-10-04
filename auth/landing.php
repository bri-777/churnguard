<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Churn Prediction System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --black: #1a1a1a;
      --white: #ffffff;
      --gray: #6b7280;
      --light-gray: #e5e7eb;
      --dark-gray: #4b5563;
      --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
      --shadow-md: 0 6px 12px rgba(0, 0, 0, 0.1);
      --shadow-lg: 0 12px 24px rgba(0, 0, 0, 0.15);
      --transition: all 0.3s ease;
      --gradient: linear-gradient(135deg, #2c2c2c, #1a1a1a);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      background-color: var(--white);
      color: var(--black);
      font-family: 'Inter', -apple-system, sans-serif;
      
      overflow-x: hidden;
    }

    .button {
      background-color: var(--black);
      color: var(--white);
      padding: 14px 28px;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      text-align: center;
      display: inline-block;
      text-decoration: none;
    }

    .button:hover {
      background: var(--gradient);
      box-shadow: var(--shadow-lg);
      transform: translateY(-2px);
    }

    .button.secondary {
      background: var(--white);
      color: var(--black);
      border: 1px solid var(--black);
      text-decoration: none;
    }

    .button.secondary:hover {
      background: var(--light-gray);
      box-shadow: var(--shadow-md);
    }

    .fade-in {
      animation: fadeIn 1.5s ease-in-out;
    }

    @keyframes fadeIn {
      0% {
        opacity: 0;
        transform: translateY(30px);
      }

      100% {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .hover-scale {
      transition: var(--transition);
    }

    .hover-scale:hover {
      transform: scale(1.02);
      box-shadow: var(--shadow-md);
    }

    header {
      background-color: var(--white);
      padding: 20px 40px;
      position: sticky;
      top: 0;
      z-index: 100;
      transition: var(--transition);
      box-shadow: var(--shadow-sm);
    }

    header.scrolled {
      padding: 15px 30px;
      box-shadow: var(--shadow-md);
    }

    nav {
      display: flex;
      align-items: center;
      max-width: 1280px;
      margin: 0 auto;
      justify-content: space-between;
    }

    nav h1 {
      font-size: 1.8rem;
      font-weight: 700;
      font-family: 'Poppins', sans-serif;
      background: var(--black);
      background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .logo h1 {
      font-size: 1.8rem;
      font-weight: 700;
      font-family: 'Poppins', sans-serif;
      background: var(--white);
      background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    nav ul {
      display: flex;
      list-style: none;
      align-items: center;
    }

    nav ul li {
      margin-left: 35px;
    }

    nav ul li a {
      color: var(--black);
      text-decoration: none;
      font-size: 15px;
      font-weight: 600;
      text-transform: uppercase;
      position: relative;
      transition: var(--transition);
    }

    nav ul li a:not(.button):hover {
      color: var(--dark-gray);
    }

    nav ul li a:not(.button)::before {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      width: 0;
      height: 2px;
      background-color: var(--black);
      transition: width 0.3s ease;
    }

    nav ul li a:not(.button):hover::before {
      width: 100%;
    }

    .menu-toggle {
      display: none;
      cursor: pointer;
      z-index: 1000;
      position: relative;
    }

    .bar {
      width: 28px;
      height: 3px;
      background-color: var(--dark-gray);
      margin: 6px 0;
      transition: var(--transition);
    }

    .menu-toggle.active .bar:nth-child(1) {
      transform: rotate(-45deg) translate(-6px, 6px);
    }

    .menu-toggle.active .bar:nth-child(2) {
      opacity: 0;
    }

    .menu-toggle.active .bar:nth-child(3) {
      transform: rotate(45deg) translate(-6px, -6px);
    }

    .dropdown {
      position: relative;
    }

    .dropdown-content {
      display: none;
      position: absolute;
      background-color: var(--white);
      min-width: 120px;
      box-shadow: var(--shadow-md);
      border-radius: 6px;
      top: 100%;
      right: 0;
      z-index: 1000;
    }

    .dropdown:hover .dropdown-content {
      display: block;
    }

    .dropdown-content a {
      display: block;
      padding: 10px 15px;
      color: var(--black);
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      text-transform: none;
    }

    .dropdown-content a:hover {
      background-color: var(--light-gray);
      color: var(--black);
    }

    .search-container {
      display: flex;
      align-items: center;
      position: relative;
      width: 100%;
      max-width: 300px;
    }

    .search-container input {
      width: 100%;
      padding: 12px 40px 12px 20px;
      border: 1px solid var(--light-gray);
      border-radius: 25px;
      font-size: 15px;
      font-family: 'Inter', sans-serif;
      background-color: var(--light-gray);
      transition: var(--transition);
      box-shadow: var(--shadow-sm);
    }

    .search-container input:focus {
      outline: none;
      border-color: var(--black);
      background-color: var(--white);
      box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.05);
    }

    .search-container i {
      position: absolute;
      right: 15px;
      color: var(--gray);
      font-size: 16px;
      pointer-events: none;
    }

    .secondary-nav {
      background-color: var(--white);
      padding: 10px 40px;
      border-top: 1px solid var(--light-gray);
      display: none;
      justify-content: flex-end;
      max-width: 1280px;
      margin: 0 auto;
    }

    section {
      padding: 80px 20px;
    }

    .hero {
      position: relative;
      padding: 150px 20px;
      text-align: center;
      text-decoration: none;
      overflow: hidden;
    }

    .hero video {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      z-index: -1;
      opacity: 1;
    }

    .hero .overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.4);
      z-index: -1;
    }

    .hero h1 {
      font-size: 56px;
      font-weight: 900;
      color: var(--white);
      margin-bottom: 25px;
      letter-spacing: -1px;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .hero p {
      font-size: 22px;
      color: var(--white);
      max-width: 700px;
      margin: 0 auto 40px;
      font-weight: 400;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    }

    .hero .buttons {
      display: flex;
      justify-content: center;
      gap: 20px;
      text-decoration: none;
    }

    .category-section {
      max-width: 1280px;
      margin: 0 auto;
      text-align: center;
      margin-bottom: -50px;
    }

    .category-section h2 {
      font-size: 42px;
      font-weight: 800;
      color: var(--black);
      margin-bottom: 50px;
      letter-spacing: -0.5px;
    }

    .category-grid {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 30px;
    }

    .category-card {
      position: relative;
      border-radius: 12px;
      overflow: hidden;
      transition: var(--transition);
      cursor: pointer;
      box-shadow: var(--shadow-sm);
    }

    .category-card img {
      width: 100%;
      height: 180px;
      object-fit: cover;
      transition: var(--transition);
    }

    .category-card .overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 1;
      transition: var(--transition);
    }

    .category-card:hover .overlay {
      opacity: 1;
    }

    .category-card:hover img {
      transform: scale(1.05);
    }

    .category-card h3 {
      font-size: 20px;
      font-weight: 700;
      color: var(--white);
      text-transform: uppercase;
      letter-spacing: 1px;
      z-index: 1;
    }

    .shop,
    .about,
    .team,
    .contact {
      max-width: 1280px;
      margin: 0 auto;
    }

    .shop h2,
    .about h2,
    .team h2,
    .contact h2 {
      font-size: 42px;
      font-weight: 800;
      color: var(--black);
      text-align: center;
      margin-bottom: 50px;
      letter-spacing: -0.5px;
    }

    .shop .grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 40px;
    }

    .product-card {
      background-color: var(--white);
      border: 1px solid var(--light-gray);
      border-radius: 12px;
      padding: 25px;
      text-align: center;
      transition: var(--transition);
    }

    .product-card img {
      width: 100%;
      height: 280px;
      object-fit: cover;
      border-radius: 8px;
      margin-bottom: 20px;
    }

    .product-card h3 {
      font-size: 22px;
      font-weight: 700;
      color: var(--black);
      margin-bottom: 12px;
    }

    .product-card p {
      font-size: 18px;
      color: var(--gray);
      margin-bottom: 20px;
    }

    .product-card .button {
      width: 100%;
    }

    .about .content {
      display: flex;
      gap: 40px;
      align-items: stretch;
      justify-content: space-between;
      min-height: 400px;
    }

    .about .content > div {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .about p {
      font-size: 18px;
      color: var(--gray);
      margin-bottom: 25px;
      font-weight: 400;
    }

    .about .card {
      background-color: var(--white);
      border: 1px solid var(--light-gray);
      border-radius: 12px;
      padding: 30px;
      position: relative;
      box-shadow: 0 0 10px #e5e7eb;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .about .card h3 {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 15px;
    }

    .team .grid {
      width: 800px;
      display: grid;
      margin: auto;
      grid-template-columns: repeat(3, 1fr);
      gap: 40px;
    }

    .grid {
      justify-content: center;
      align-items: center;
    }

    .team-card {
      background-color: var(--white);
      border-radius: 12px;
      padding: 25px;
      text-align: center;
      transition: var(--transition);
    }

    .team-card img {
      width: 150px;
      height: 150px;
      object-fit: cover;
      border-radius: 50%;
      margin: 0 auto 20px;
      display: block;
      transition: var(--transition);
    }

    .team-card img:hover {
      transform: scale(1.02);
    }

    .team-card h3 {
      font-size: 22px;
      font-weight: 700;
      color: var(--black);
      margin-bottom: 12px;
    }

    .team-card p.position {
      font-size: 18px;
      color: var(--gray);
      margin-bottom: 20px;
    }

    .team-card .social-links {
      display: flex;
      justify-content: center;
      gap: 10px;
    }

    .team-card .social-link {
      width: 36px;
      height: 36px;
      background-color: var(--light-gray);
      border-radius: 50%;
      display: flex;
      justify-content: center;
      align-items: center;
      color: var(--black);
      transition: var(--transition);
      font-size: 16px;
      text-decoration: none;
    }

    .team-card .social-link:hover {
      background-color: var(--black);
      color: var(--white);
      transform: translateY(-2px);
    }

    .contact .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 40px;
      align-items: stretch;
    }

    #about {
      margin-top: -50px;
    }

    #team {
      margin-top: -50px;
    }

    #contact {
      margin-top: -80px;
    }

    .contact .info h3,
    .contact .form h3 {
      font-size: 26px;
      font-weight: 700;
      color: var(--black);
      margin-bottom: 20px;
    }

    .contact .info p {
      font-size: 18px;
      color: var(--gray);
      margin-bottom: 15px;
      font-weight: 400;
    }

    .contact .form {
      background-color: var(--white);
      border: 1px solid var(--light-gray);
      border-radius: 12px;
      padding: 30px;
      transition: var(--transition);
      box-shadow: 0 0 10px #e5e7eb;
    }

    .contact .form input,
    .contact .form textarea {
      width: 100%;
      padding: 14px;
      margin-bottom: 25px;
      border: 2px solid var(--light-gray);
      border-radius: 8px;
      font-size: 16px;
      transition: var(--transition);
      font-family: 'Inter', sans-serif;
      background-color: var(--white);
    }

    .contact .form input:focus,
    .contact .form textarea:focus {
      outline: none;
      border-color: var(--black);
      box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.05);
    }

    .contact .form .button {
      width: 100%;
      padding: 14px;
      font-size: 15px;
    }

    .faq {
      background-color: #f9f9f9;
      color: var(--black);
      padding: 60px 20px;
    }

    .faq h2 {
      font-size: 42px;
      font-weight: 800;
      text-align: center;
      margin-bottom: 20px;
      letter-spacing: -0.5px;
    }

    .faq p {
      font-size: 18px;
      color: var(--gray);
      text-align: center;
      margin-bottom: 40px;
      max-width: 800px;
      margin-left: auto;
      margin-right: auto;
    }

    .faq .grid {
      max-width: 1280px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }

    .faq-item {
      background-color: #f9f9f9;
      border: 1px solid var(--light-gray);
      border-radius: 8px;
      padding: 15px;
      cursor: pointer;
      transition: var(--transition);
    }

    .faq-item:hover {
      background-color: var(--light-gray);
    }

    .faq-item h3 {
      font-size: 18px;
      font-weight: 600;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .faqQuestion .arrow {
      float: right;
      transition: transform 0.3s ease-in-out;
    }

    .faq-item .content {
      display: none;
      margin-top: 10px;
      font-size: 16px;
      color: var(--gray);
    }

    .faq-item.active .content {
      display: block;
    }

    .arrow {
      transform: rotate(90deg);
    }

    .faq-item.active .arrow {
      transform: rotate(180deg);
    }

    .footer {
      background: var(--black);
      color: var(--white);
      padding: 80px 20px;
    }

    .footer-container {
      max-width: 1280px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 3fr 1fr 1fr;
      gap: 40px;
      margin-bottom: 40px;
    }

    .brand-section {
      display: flex;
      flex-direction: column;
      gap: 25px;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 15px;
      font-size: 27px;
      font-weight: 800;
      color: var(--white);
    }

    .logo svg {
      width: 50px;
      height: 50px;
      border-radius: 10px;
      background-color: var(--white);
      padding: 10px;
      fill: var(--black);
    }

    .brand-description {
      font-size: 18px;
      color: var(--light-gray);
      max-width: 320px;
      font-weight: 400;
    }

    .footer-column h4 {
      font-size: 18px;
      font-weight: 700;
      color: var(--white);
      margin-bottom: 25px;
      position: relative;
      padding-bottom: 12px;
    }

    .footer-column h4::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 50px;
      height: 3px;
      background-color: var(--white);
      border-radius: 2px;
    }

    .footer-links {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .footer-links a {
      color: var(--light-gray);
      text-decoration: none;
      font-size: 16px;
      transition: var(--transition);
      font-weight: 400;
    }

    .footer-links a:hover {
      color: var(--white);
      transform: translateX(5px);
    }

    .social-links {
      display: flex;
      gap: 15px;
      margin-bottom: 25px;
    }

    .social-link {
      width: 44px;
      height: 44px;
      background-color: var(--dark-gray);
      border-radius: 8px;
      display: flex;
      justify-content: center;
      align-items: center;
      color: var(--white);
      transition: var(--transition);
      font-size: 18px;
      text-decoration: none;
    }

    .social-link:hover {
      background-color: var(--white);
      color: var(--black);
      transform: translateY(-4px);
    }

    .footer-bottom {
      border-top: 1px solid var(--light-gray);
      padding-top: 25px;
      text-align: center;
    }

    .copyright {
      font-size: 16px;
      color: var(--light-gray);
      font-weight: 400;
    }

    .copyright a {
      color: var(--white);
      text-decoration: none;
    }

    .copyright a:hover {
      text-decoration: underline;
    }

    @media (max-width: 992px) {
      .hero h1 {
        font-size: 42px;
      }

      .hero p {
        font-size: 18px;
      }

      .category-grid {
        grid-template-columns: repeat(3, 1fr);
      }

      .shop .grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .team .grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .contact .grid {
        grid-template-columns: 1fr;
      }

      .contact .form {
        margin-top: 30px;
      }

      .faq .grid {
        grid-template-columns: 1fr;
      }

      .about .content {
        min-height: auto;
      }
    }

    @media (max-width: 768px) {
      header {
        padding: 15px 20px;
      }

      nav ul {
        position: fixed;
        top: 0;
        right: -100%;
        width: 70%;
        height: 100vh;
        background: var(--gradient);
        flex-direction: column;
        justify-content: center;
        align-items: center;
        transition: right 0.5s ease;
        z-index: 999;
      }

      nav ul.active {
        right: 0;
      }

      nav ul li {
        margin: 25px 0;
      }

      nav ul li a {
        color: var(--white);
        font-size: 18px;
      }

      nav ul li.search-container {
        display: none;
      }

      .menu-toggle {
        display: block;
      }

      .secondary-nav {
        display: flex;
        padding: 10px 20px;
      }

      .search-container {
        max-width: 100%;
      }

      .hero {
        padding: 80px 20px;
      }

      .hero h1 {
        font-size: 36px;
      }

      .hero p {
        font-size: 16px;
      }

      .hero .buttons {
        flex-direction: column;
        gap: 15px;
      }

      .hero .button {
        padding: 10px 20px;
        font-size: 14px;
      }

      .category-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .shop h2,
      .about h2,
      .team h2,
      .contact h2 {
        font-size: 32px;
      }

      .shop .grid {
        grid-template-columns: 1fr;
      }

      .team .grid {
        grid-template-columns: 1fr;
      }

      .about .content {
        flex-direction: column;
      }

      .about .content > div {
        width: 100%;
      }

      .footer {
        padding: 50px 20px;
      }

      .footer-container {
        grid-template-columns: 1fr;
        text-align: center;
      }

      .footer-column h4::after {
        left: 50%;
        transform: translateX(-50%);
      }

      .footer-links {
        align-items: center;
      }

      .logo {
        flex-direction: column;
        text-align: center;
      }

      .hero video {
        display: none;
      }

      .hero {
        background: var(--light-gray);
      }

      .hero h1,
      .hero p {
        color: var(--black);
        text-shadow: none;
      }
    }

    @media (max-width: 576px) {
      section {
        padding: 50px 15px;
      }

      .hero h1 {
        font-size: 30px;
      }

      .hero p {
        font-size: 15px;
      }

      .hero .button {
        padding: 8px 16px;
        font-size: 13px;
      }

      .category-grid {
        grid-template-columns: 1fr;
      }

      .shop h2,
      .about h2,
      .team h2,
      .contact h2 {
        font-size: 26px;
      }

      .contact .info h3,
      .contact .form h3 {
        font-size: 22px;
      }

      .footer {
        padding: 40px 15px;
      }

      .copyright {
        font-size: 14px;
      }
    }

    @media (max-width: 480px) {
      header {
        padding: 15px 20px;
      }

      nav ul {
        position: fixed;
        top: 0;
        right: -100%;
        width: 70%;
        height: 100vh;
        background: var(--gradient);
        flex-direction: column;
        justify-content: center;
        align-items: center;
        /* transition: right 0.5s ease; */
        z-index: 999;
      }

      nav ul.active {
        right: 0;
      }

      nav ul li {
        margin: 25px 0;
      }

      nav ul li a {
        color: var(--white);
        font-size: 18px;
      }

      nav ul li.search-container {
        display: none;
      }

      .menu-toggle {
        display: block;
      }

      .secondary-nav {
        display: flex;
        padding: 10px 20px;
      }

      .search-container {
        max-width: 100%;
      }

      .hero h1 {
        font-size: 26px;
      }

      .hero p {
        font-size: 14px;
      }

      .hero .button {
        padding: 6px 12px;
        font-size: 12px;
      }

      .shop h2,
      .category-section h2,
      .about h2,
      .team h2,
      .contact h2 {
        font-size: 22px;
      }

      .contact .info h3,
      .contact .form h3 {
        font-size: 20px;
      }
    }
  </style>
  
  <style>
.about {
  padding: 100px 20px;
  max-width: 1200px;
  margin: 0 auto;
  background: #ffffff;
}

.about h2 {
  font-size: 2.5rem;
  font-weight: 700;
  text-align: center;
  margin-bottom: 60px;
  color: #1e293b;
  letter-spacing: -0.02em;
}

.content {
  display: grid;
  grid-template-columns: 1.2fr 1fr;
  gap: 50px;
  align-items: start;
}

.content > div:first-child {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 40px;
}

.content p {
  font-size: 1.1rem;
  color: #64748b;
  margin-bottom: 20px;
  line-height: 1.7;
}

.content h3 {
  font-size: 1.3rem;
  font-weight: 600;
  color: #0f172a;
  margin: 30px 0 15px 0;
}

.button {
  display: inline-block;
  background: #3b82f6;
  color: white;
  text-decoration: none;
  padding: 14px 28px;
  border-radius: 8px;
  font-weight: 500;
  font-size: 1rem;
  transition: background-color 0.2s ease;
  margin-top: 10px;
}

.button:hover {
  background: #2563eb;
}

.card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 35px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
}

.card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
}

.card h3 {
  color: #3b82f6;
  margin-bottom: 12px;
  font-size: 1.2rem;
  font-weight: 600;
}

.card p {
  color: #475569;
  font-size: 1rem;
  margin-bottom: 25px;
  line-height: 1.6;
}

.fade-in {
  animation: fadeInUp 0.8s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.hover-scale {
  transition: transform 0.3s ease;
}

@media (max-width: 768px) {
  .content {
    grid-template-columns: 1fr;
    gap: 30px;
  }
  
  .about {
    padding: 60px 20px;
  }

  .about h2 {
    font-size: 2rem;
    margin-bottom: 40px;
  }

  .content > div:first-child,
  .card {
    padding: 25px;
  }
}
</style>
<!-- about design -->


<style>


.contact h2 {
  font-size: 2.5rem;
  font-weight: 700;
  text-align: center;
  margin-bottom: 60px;
  color: #1e293b;
  letter-spacing: -0.02em;
}

.grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 60px;
  align-items: start;
}

.info {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 40px;
}

.info h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #0f172a;
  margin-bottom: 15px;
}

.info > p {
  font-size: 1.1rem;
  color: #64748b;
  line-height: 1.6;
  margin-bottom: 30px;
}

.contact-item {
  font-size: 1rem;
  color: #475569;
  margin-bottom: 15px;
  padding: 12px 0;
  border-bottom: 1px solid #e2e8f0;
}

.contact-item:last-of-type {
  border-bottom: none;
  margin-bottom: 25px;
}

.contact-item strong {
  color: #1e293b;
  display: inline-block;
  width: 70px;
  font-weight: 600;
}

.response-time {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.9rem;
  color: #059669;
  background: #f0fdf4;
  padding: 12px 16px;
  border-radius: 8px;
  border: 1px solid #bbf7d0;
}

.status-indicator {
  width: 8px;
  height: 8px;
  background: #10b981;
  border-radius: 50%;
  animation: pulse-green 2s infinite;
}

@keyframes pulse-green {
  0% { opacity: 1; }
  50% { opacity: 0.5; }
  100% { opacity: 1; }
}

.form {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 40px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.form h3 {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1e293b;
  margin-bottom: 25px;
}

.form input,
.form select,
.form textarea {
  width: 100%;
  padding: 14px 16px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  font-size: 1rem;
  margin-bottom: 20px;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
  font-family: inherit;
}

.form input:focus,
.form select:focus,
.form textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form select {
  cursor: pointer;
  background-color: #ffffff;
}

.form select option {
  padding: 10px;
}

.form textarea {
  resize: vertical;
  min-height: 100px;
}



.button:hover {
  background: #2563eb;
  transform: translateY(-1px);
}

.button:active {
  transform: translateY(0);
}

.fade-in {
  animation: fadeInUp 0.8s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 768px) {
  .grid {
    grid-template-columns: 1fr;
    gap: 40px;
  }
  
  .contact {
    padding: 60px 20px;
  }

  .contact h2 {
    font-size: 2rem;
    margin-bottom: 40px;
  }

  .info,
  .form {
    padding: 30px;
  }

  .contact-item strong {
    width: 60px;
  }
}

@media (max-width: 480px) {
  .info,
  .form {
    padding: 25px;
  }
  
  .contact-item {
    font-size: 0.95rem;
  }
  
  .contact-item strong {
    display: block;
    margin-bottom: 5px;
    width: auto;
  }
}
</style> 
  
  <!-- Contact Design -->
  
  
  <style>
.footer {
  background: #0f172a;
  color: #e2e8f0;
  padding: 60px 0 0 0;
  margin-top: 80px;
}

.footer-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 50px;
  margin-bottom: 40px;
}

.brand-section {
  max-width: 350px;
}

.logo {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}

.logo svg {
  width: 32px;
  height: 32px;
  color: #3b82f6;
}

.logo h1 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #ffffff;
  margin: 0;
}

.brand-description {
  color: #94a3b8;
  line-height: 1.6;
  margin-bottom: 25px;
  font-size: 0.95rem;
}

.social-links {
  display: flex;
  gap: 12px;
}

.social-link {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 8px;
  color: #94a3b8;
  text-decoration: none;
  transition: all 0.2s ease;
}

.social-link:hover {
  background: #3b82f6;
  border-color: #3b82f6;
  color: #ffffff;
  transform: translateY(-2px);
}

.social-link svg {
  width: 18px;
  height: 18px;
}

.footer-column h4 {
  color: #ffffff;
  font-size: 1.1rem;
  font-weight: 600;
  margin-bottom: 20px;
}

.footer-links {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.footer-links a {
  color: #94a3b8;
  text-decoration: none;
  font-size: 0.95rem;
  transition: color 0.2s ease;
}

.footer-links a:hover {
  color: #3b82f6;
}

.footer-bottom {
  border-top: 1px solid #1e293b;
  padding: 25px 0;
}

.footer-bottom-content {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
}

.copyright {
  color: #64748b;
  font-size: 0.9rem;
}

.copyright a {
  color: #3b82f6;
  text-decoration: none;
}

.copyright a:hover {
  text-decoration: underline;
}

.legal-links {
  display: flex;
  gap: 25px;
}

.legal-links a {
  color: #64748b;
  text-decoration: none;
  font-size: 0.9rem;
  transition: color 0.2s ease;
}

.legal-links a:hover {
  color: #3b82f6;
}

@media (max-width: 968px) {
  .footer-container {
    grid-template-columns: 1fr 1fr;
    gap: 40px;
  }
  
  .brand-section {
    max-width: none;
  }
}

@media (max-width: 640px) {
  .footer-container {
    grid-template-columns: 1fr;
    gap: 35px;
  }
  
  .footer {
    padding: 40px 0 0 0;
  }
  
  .footer-bottom-content {
    flex-direction: column;
    text-align: center;
    gap: 15px;
  }
  
  .legal-links {
    gap: 20px;
  }
}

@media (max-width: 480px) {
  .social-links {
    justify-content: center;
  }
  
  .legal-links {
    flex-wrap: wrap;
    justify-content: center;
    gap: 15px;
  }
}
</style>
  
  <!-- Footer -->
  
  
  
  

<style>
.buttons {
  display: flex;
  gap: 24px;
  justify-content: center;
  align-items: center;
  flex-wrap: wrap;
  margin: 48px 0;
}

.button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 18px 36px;
  font-size: 1rem;
  font-weight: 600;
  text-decoration: none;
  border-radius: 16px;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
  overflow: hidden;
  min-width: 180px;
  text-align: center;
  letter-spacing: -0.01em;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
}

.button-text {
  position: relative;
  z-index: 3;
}

.button-icon {
  position: relative;
  z-index: 3;
  display: flex;
  align-items: center;
  transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

/* Animated border effect */
.button::before {
  content: '';
  position: absolute;
  inset: 0;
  padding: 2px;
  background: linear-gradient(135deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  border-radius: 16px;
  mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  mask-composite: xor;
  -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor;
  opacity: 0;
  transition: opacity 0.3s ease;
}

/* Glow effect */
.button::after {
  content: '';
  position: absolute;
  inset: -2px;
  background: radial-gradient(circle at center, rgba(59, 130, 246, 0.15), transparent 70%);
  border-radius: 18px;
  z-index: 1;
  opacity: 0;
  transition: opacity 0.3s ease;
}

/* Primary Button - Glass Blue */
.button.primary {
  background: rgba(59, 130, 246, 0.1);
  color: #ffffff;
  border: 1px solid rgba(59, 130, 246, 0.3);
  box-shadow: 
    0 8px 32px rgba(59, 130, 246, 0.12),
    inset 0 1px 0 rgba(255, 255, 255, 0.1),
    inset 0 -1px 0 rgba(59, 130, 246, 0.2);
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

.button.primary:hover {
  background: rgba(59, 130, 246, 0.15);
  border-color: rgba(59, 130, 246, 0.5);
  transform: translateY(-3px);
  box-shadow: 
    0 16px 48px rgba(59, 130, 246, 0.2),
    inset 0 1px 0 rgba(255, 255, 255, 0.15),
    inset 0 -1px 0 rgba(59, 130, 246, 0.3);
}

.button.primary:hover::before {
  opacity: 1;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(59, 130, 246, 0.2), rgba(255, 255, 255, 0.1));
}

.button.primary:hover::after {
  opacity: 1;
}

.button.primary:hover .button-icon {
  transform: translateX(4px);
}

.button.primary:active {
  transform: translateY(-1px);
  background: rgba(59, 130, 246, 0.2);
}

/* Secondary Button - Glass White */
.button.secondary {
  background: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.9);
  border: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: 
    0 8px 32px rgba(0, 0, 0, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.1),
    inset 0 -1px 0 rgba(255, 255, 255, 0.05);
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.button.secondary:hover {
  background: rgba(255, 255, 255, 0.12);
  border-color: rgba(255, 255, 255, 0.3);
  color: #ffffff;
  transform: translateY(-2px);
  box-shadow: 
    0 12px 40px rgba(0, 0, 0, 0.15),
    inset 0 1px 0 rgba(255, 255, 255, 0.15),
    inset 0 -1px 0 rgba(255, 255, 255, 0.1);
}

.button.secondary:hover::before {
  opacity: 1;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.15));
}

.button.secondary:hover::after {
  opacity: 1;
  background: radial-gradient(circle at center, rgba(255, 255, 255, 0.08), transparent 70%);
}

.button.secondary:hover .button-icon {
  transform: translate(3px, -3px);
}

.button.secondary:active {
  transform: translateY(0);
  background: rgba(255, 255, 255, 0.15);
}

/* Enhanced glass effects for both buttons */
.button:hover {
  backdrop-filter: blur(25px);
  -webkit-backdrop-filter: blur(25px);
}

/* Shimmer animation */
@keyframes shimmer {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}

.button:hover::before {
  animation: shimmer 1.5s ease-in-out infinite;
}

/* Focus states */
.button:focus-visible {
  outline: 2px solid rgba(59, 130, 246, 0.6);
  outline-offset: 2px;
}

/* Loading state */
.button.loading {
  pointer-events: none;
  opacity: 0.7;
}

.button.loading .button-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Entrance animation */
.fade-in {
  animation: fadeInScale 1.2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes fadeInScale {
  from {
    opacity: 0;
    transform: translateY(40px) scale(0.9);
    filter: blur(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
    filter: blur(0px);
  }
}

/* Floating particles effect */
.buttons::before {
  content: '';
  position: absolute;
  width: 200px;
  height: 200px;
  background: radial-gradient(circle, rgba(59, 130, 246, 0.1), transparent 70%);
  border-radius: 50%;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  animation: float 4s ease-in-out infinite;
  pointer-events: none;
  z-index: 0;
}

@keyframes float {
  0%, 100% { transform: translate(-50%, -50%) scale(1); }
  50% { transform: translate(-50%, -60%) scale(1.1); }
}

/* Responsive design */
@media (max-width: 640px) {
  .buttons {
    flex-direction: column;
    gap: 20px;
    margin: 36px 0;
  }
  
  .button {
    min-width: 240px;
    padding: 16px 32px;
  }
}

/* Dark background optimization */
@media (prefers-color-scheme: dark) {
  .button.primary {
    background: rgba(59, 130, 246, 0.12);
    border-color: rgba(59, 130, 246, 0.4);
  }
  
  .button.secondary {
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(255, 255, 255, 0.15);
    color: rgba(255, 255, 255, 0.85);
  }
}

/* Reduced motion */
@media (prefers-reduced-motion: reduce) {
  .button,
  .button-icon,
  .fade-in {
    transition: none;
    animation: none;
  }
  
  .button::before {
    animation: none;
  }
}
</style>

 <!-- Button Design in front -->
  
</head>

<body>
  <!-- Header -->
  <header>
    <nav>
	<br><br>
      <h1 style="font-family: 'Inter', 'Poppins', Arial, sans-serif;
           font-size: 2.4em;
           font-weight: 700;
           text-align: center;
           margin: 40px 0 5px 0;
           color: #222;
           letter-spacing: 1px;">
  <span style="color:#ff6600;">ChurnGuard</span> 
  <span style="font-weight: 400; color:#444;">Pro</span>
</h1>

<p style="text-align: center; 
          font-family: 'Inter', Arial, sans-serif;
          font-size: 0.9em; 
          color: #777; 
          margin-top: 0;">
  
</p><br><br><br><br>

      <div class="menu-toggle">
        <div class="bar"></div>
        <div class="bar"></div>
        <div class="bar"></div>
      </div>
  <ul style="list-style:none; margin:0; padding:14px 40px; display:flex; justify-content:flex-end; align-items:center; background:#fff; font-family:Arial, sans-serif; font-size:16px;">

  <li style="margin:0 20px;">
    <a href="#home" style="text-decoration:none; color:#000; padding:10px 12px; display:inline-block;">Home</a>
  </li>

  <li style="margin:0 20px;">
    <a href="#about" style="text-decoration:none; color:#000; padding:10px 12px; display:inline-block;">About</a>
  </li>

  <li style="margin:0 20px;">
    <a href="#contact" style="text-decoration:none; color:#000; padding:10px 12px; display:inline-block;">Contact</a>
  </li>

  <li class="dropdown" style="position:relative; margin:0 20px;">
    <a href="#" id="userMenu" style="color:#000; font-size:18px; padding:8px 12px; display:inline-block;">
      <i class="fas fa-user"></i>
    </a>
    <div class="dropdown-content" style="display:none; position:absolute; right:0; top:45px; background:#fff; border-radius:8px; min-width:160px; box-shadow:0 6px 15px rgba(0,0,0,0.15); overflow:hidden;">
      <a href="/auth/login.php" style="display:block; padding:12px 15px; color:#000; text-decoration:none;">Login</a>
      <a href="/auth/signup.php" style="display:block; padding:12px 15px; color:#000; text-decoration:none;">Sign Up</a>
    </div>
  </li>
</ul>

<script>
  // Toggle dropdown on click
  document.querySelectorAll('.dropdown > a').forEach(function(button){
    button.addEventListener('click', function(e){
      e.preventDefault();
      let dropdown = this.nextElementSibling;

      // Close other dropdowns first
      document.querySelectorAll('.dropdown-content').forEach(function(dc){
        if(dc !== dropdown) dc.style.display = 'none';
      });

      // Toggle clicked dropdown
      dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
    });
  });

  // Close dropdown if click outside
  document.addEventListener('click', function(e){
    if(!e.target.closest('.dropdown')){
      document.querySelectorAll('.dropdown-content').forEach(function(dc){
        dc.style.display = 'none';
      });
    }
  });
</script>


    </nav>
 
  </header>

  <!-- Hero Section -->
  <section id="home" class="hero">
    <video autoplay muted loop playsinline>
      <source src="../assets/video/vid.mp4" type="video/mp4">
      Your browser does not support the video tag.
    </video>
    <div class="overlay"></div>
    <h1 class="fade-in"><br><br></h1>
    <p class="fade-in"></p>
   
<div class="buttons fade-in">
  <a href="/auth/signup.php" class="button primary">
    <span class="button-text">Get Started</span>
    <span class="button-icon">→</span>
  </a>
  <a href="#about" class="button secondary">
    <span class="button-text">Learn More</span>
    <span class="button-icon">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M7 17L17 7M17 7H7M17 7V17"/>
      </svg>
    </span>
  </a>
</div>
  </section>
<br><br><br>





<!-- About Section -->
<section id="about" class="about" aria-labelledby="about-title">
  <h2 id="about-title" class="fade-in">About ChurnGuard Pro</h2>

  <div class="content about-grid">
    <!-- Left: concise system summary -->
    <div class="about-copy fade-in">
      <p>
        ChurnGuard Analytics is a real time churn prediction system for Philippine convenience stores.
        It reads POS and shift logs, scores churn risk, and suggests simple actions so staff can respond early.
      </p>

      <h3>What the system does</h3>
      <ul class="keypoints">
        <li>Turns receipts and shift performance into churn risk scores for 7, 14, and 30 day windows</li>
        <li>Highlights at risk segments and the hours or days that need attention</li>
        <li>Suggests actions like quick promos or shift review with clear next steps</li>
        <li>Updates continuously and works across branches even with spotty connectivity</li>
      </ul>

      <h3>Data it uses</h3>
      <ul class="keypoints">
        <li>Store level logs only: customer or receipt id, store id, timestamp, total, items, promo id</li>
        <li>Optional signals: e wallet top ups, loyalty points, delivery orders</li>
        <li>No personal profiling required to run</li>
      </ul>
    </div>

    <!-- Right: compact cards -->
    <div class="about-side fade-in">
      <div class="card hover-scale">
        <h3 class="card-title">Core features</h3>
        <ul class="mini-list">
          <li>Risk scores by store and shift</li>
          <li>At risk segment highlights</li>
          <li>Alerts with suggested actions</li>
          <li>Simple trend views for sales and receipts</li>
        </ul>
      </div>

      <div class="card hover-scale">
        <h3 class="card-title">How it works</h3>
        <ol class="mini-ol">
          <li>Ingest POS logs and clean fields</li>
          <li>Engineer features from visits, time gaps, basket mix, and promo response</li>
          <li>Score with XGBoost and refresh in near real time</li>
        </ol>
      </div>

      <div class="card hover-scale">
        <h3 class="card-title">What managers see</h3>
        <ul class="mini-list">
          <li>Shift and day risk summary</li>
          <li>Top hours to watch</li>
          <li>Action tips ready for staff</li>
        </ul>
      </div>

   
    </div>
  </div>
</section>

<style>

/* ===== About (right aligned, simple, airy) ===== */

/* One knob to control width/right bias */
#about.about {
 #about.about { --about-width: 900px; }   /* or 780px for a stronger right bias */

  width: var(--about-width);
  margin-left: auto;                          /* right align the whole block */
  padding: clamp(20px, 4vw, 48px);
}

/* Slight extra nudge on ultrawide screens */
@media (min-width: 1280px) {
  #about.about { transform: translateX(12px); }
}

/* Grid: balanced and breathable */
#about .about-grid {
  display: grid;
  grid-template-columns: 1.2fr .8fr;
  gap: clamp(24px, 3vw, 36px);
  align-items: start;
}
@media (max-width: 960px) { 
  #about .about-grid { grid-template-columns: 1fr; }
}

/* Type: modern and easy to read (keeps your colors) */
#about, #about .content {
  font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI",
               Inter, Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
  -webkit-font-smoothing: antialiased;
  text-rendering: optimizeLegibility;
}
#about-title {
  margin: 0 0 16px;
  line-height: 1.15;
  font-weight: 800;
  font-size: clamp(1.35rem, 1rem + 1.2vw, 1.9rem);
}
#about h3, #about .card-title {
  margin: 0 0 12px;
  line-height: 1.2;
  font-weight: 700;
  font-size: clamp(1.06rem, .95rem + .6vw, 1.35rem);
}
#about .about-copy p {
  margin: 0 0 14px;
  line-height: 1.75;
  font-size: clamp(1rem, .96rem + .35vw, 1.1rem);
  letter-spacing: .1px;
}

/* Lists: comfy spacing */
#about .keypoints,
#about .mini-list,
#about .mini-ol { margin: 10px 0 0 18px; }
#about .keypoints li,
#about .mini-list li,
#about .mini-ol li { margin: 7px 0; line-height: 1.65; }

/* Cards: borderless, soft glass, subtle depth from currentColor */
#about .card {
  border: none;
  border-radius: 18px;
  padding: clamp(16px, 2.4vw, 20px);
  background: transparent;                           /* fallback */
  backdrop-filter: blur(10px) saturate(115%);
  -webkit-backdrop-filter: blur(10px) saturate(115%);
  box-shadow:
    0 12px 30px color-mix(in srgb, currentColor 16%, transparent),
    0 1px 0 color-mix(in srgb, currentColor 20%, transparent);
}
/* Soft tint if supported (uses your theme color) */
@supports (background: color-mix(in srgb, red 10%, transparent)) {
  #about .card { background: color-mix(in srgb, currentColor 6%, transparent); }
}

/* Gentle hover (no color change) */
#about .hover-scale {
  transition: transform .18s ease, box-shadow .18s ease, opacity .18s ease;
  will-change: transform;
}
#about .hover-scale:hover {
  transform: translateY(-2px);
  box-shadow:
    0 18px 44px color-mix(in srgb, currentColor 24%, transparent),
    0 1px 0 color-mix(in srgb, currentColor 28%, transparent);
  opacity: 1;
}

/* Right column tidy */
#about .about-side { 
  display: grid; 
  gap: clamp(12px, 2vw, 16px); 
  align-content: start; 
}

/* Fade-in utility already in your HTML */
#about .fade-in { 
  opacity: 0; 
  transform: translateY(4px); 
  transition: opacity .28s ease, transform .28s ease; 
}
#about .fade-in.is-visible { 
  opacity: 1; 
  transform: translateY(0); 
}

/* Links & focus (accessible, color-safe) */
#about a { text-underline-offset: 3px; text-decoration-thickness: .08em; }
#about a:focus-visible,
#about button:focus-visible,
#about .card:focus-within {
  outline: 2px solid currentColor;
  outline-offset: 4px;
  border-radius: 16px;
}

/* Respect reduced motion */
@media (prefers-reduced-motion: reduce) {
  #about .hover-scale, #about .fade-in { transition: none; }
}


</style>



<script>
  // Optional: reveal on scroll, no color changes
  const io = new IntersectionObserver((entries)=>entries.forEach(e=>{
    if (e.isIntersecting) e.target.classList.add('is-visible');
  }), {threshold: 0.12});
  document.querySelectorAll('.fade-in').forEach(el=>io.observe(el));
</script>






 <!-- Contact Section -->
<section id="contact" class="contact" aria-labelledby="contact-title">
  <h2 id="contact-title">Get in Touch</h2>

  <div class="grid">
    <!-- Left: info -->
    <div class="info">
      <h3>Ready to reduce churn?</h3>
      <p>
        We help PH convenience stores turn POS and shift logs into real-time churn risk and clear actions.
        Include a few details below so we can respond with the best setup.
      </p>

      <div class="contact-item">
        <strong>Email:</strong>
        <a href="mailto:ysl.aether.bank@gmail.com?subject=ChurnGuard%20Inquiry%20-%20Website"
           class="contact-link" id="contact-email">ysl.aether.bank@gmail.com</a>
        <button class="copy-btn" data-copy="#contact-email" aria-label="Copy email">Copy</button>
      </div>

      <div class="contact-item">
        <strong>Phone:</strong>
        <a href="tel:09120091223" class="contact-link" id="contact-phone">09120091223</a>
        <button class="copy-btn" data-copy="#contact-phone" aria-label="Copy phone">Copy</button>
      </div>

      <div class="contact-item">
        <strong>Support hours:</strong> Mon–Sat, 9:00–18:00 (PH)
      </div>

      <div class="response-time">
        <span class="status-dot" aria-hidden="true"></span>
        <span>Typical response time: 2–4 hours</span>
      </div>

      
    </div>

    <!-- Right: simple email launcher form -->
   <!-- Right: simple email launcher form -->
<div class="card" style="padding:16px; align-self:start;">
  <h3 class="card-title">Message us</h3>

  <form id="contact-form" novalidate>
    <div class="row">
      <label for="name">Name</label>
      <input id="name" name="name" type="text" required autocomplete="name" />
    </div>

    <div class="row">
      <label for="email">Your email</label>
      <input id="email" name="email" type="email" required autocomplete="email" />
    </div>

    <div class="row">
      <label for="company">Company or store</label>
      <input id="company" name="company" type="text" autocomplete="organization" />
    </div>

    <div class="row">
      <label for="message">Message</label>
      <textarea id="message" name="message" rows="5" required placeholder="Tell us about your POS, data exports, and goals"></textarea>
    </div>

    <!-- Optional honeypot -->
    <input type="text" id="website" name="website" style="display:none" tabindex="-1" autocomplete="off" />

    <button type="submit" class="button">Send email</button>
    <p id="form-status" class="muted" style="margin:8px 0 0;"></p>

    <!-- Fallback clickable link if popup blockers interfere -->
    <a id="mailtoLink" href="#" style="display:none">Click here to open your email app</a>
  </form>
</div>
  </div>
</section>

<!-- Layout-only styles (no animations, no transitions). Uses currentColor to keep your theme. -->
<style>
  .contact .grid {
    display: grid;
    gap: 24px;
    grid-template-columns: 1.15fr .85fr;
    align-items: start;
  }
  @media (max-width: 960px) { .contact .grid { grid-template-columns: 1fr; } }

  .contact-item { margin: 6px 0; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
  .contact-link { text-decoration: underline; }

  .copy-btn {
    border: 1px solid currentColor; background: transparent; color: inherit; cursor: pointer;
    padding: 2px 8px; border-radius: 8px; font-size: 12px;
  }

  .response-time { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
  .status-dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; display: inline-block; }

  /* Form */
  #contact-form .row { display: grid; gap: 6px; margin: 8px 0; }
  #contact-form input, #contact-form textarea {
    width: 100%; border: 1px solid currentColor; background: transparent; color: inherit;
    padding: 10px 12px; border-radius: 10px;
  }
  #contact-form input::placeholder, #contact-form textarea::placeholder { opacity: .7; }
  .muted { opacity: .85; font-size: 12px; }

  /* Card (no hover effects) */
  .card { border: 1px solid currentColor; border-radius: 12px; }
  .card-title { margin: 0 0 8px; }
</style>

<script>
  // Copy buttons (no animation)
  document.querySelectorAll('.copy-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const target = document.querySelector(btn.dataset.copy);
      const text = target.tagName.toLowerCase() === 'a' ? target.textContent.trim() : target.value.trim();
      navigator.clipboard.writeText(text).then(()=>{
        btn.textContent = 'Copied';
        setTimeout(()=>btn.textContent = 'Copy', 1200);
      });
    });
  });

  // Form: opens a prefilled email to ysl.aether.bank@gmail.com
  document.getElementById('contact-form').addEventListener('submit', (e)=>{
    e.preventDefault();
    const name = document.getElementById('name').value.trim();
    const fromEmail = document.getElementById('email').value.trim();
    const company = document.getElementById('company').value.trim();
   
    const message = document.getElementById('message').value.trim();

    const subject = encodeURIComponent('ChurnGuard Inquiry - Website');
    const body =
`Name: ${name}
Email: ${fromEmail}
Company: ${company || '-'}
Branches: ${branches || '-'}
Phone: 09120091223

Message:
${message}

Context:
- Churn prediction for PH convenience retail
- Data sources: POS & shift logs
- Preferred churn window: 7/14/30 days`;

    const mailto = `mailto:ysl.aether.bank@gmail.com?subject=${subject}&body=${encodeURIComponent(body)}`;
    const status = document.getElementById('form-status');
    window.location.href = mailto;
    status.textContent = 'Opening your email app. If nothing happens, click the email link above.';
  });
</script>


<!-- Footer -->
<footer class="footer">
  <div class="footer-container">
    <div class="brand-section">
      <div class="logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 3v18h18v-18h-18zm9 16c-3.86 0-7-3.14-7-7s3.14-7 7-7 7 3.14 7 7-3.14 7-7 7z"/>
          <path d="M8.5 8.5l7 7m0-7l-7 7"/>
        </svg>
        <h1>ChurnGuard Analytics</h1>
      </div>
      <p class="brand-description">Empowering businesses with AI-driven customer retention insights since 2024. Transform your churn prediction strategy with XGBoost technology.</p>
      <div class="social-links">
        <a href="#" class="social-link" aria-label="LinkedIn">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
          </svg>
        </a>
        <a href="#" class="social-link" aria-label="GitHub">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
          </svg>
        </a>
        <a href="#" class="social-link" aria-label="Twitter">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
          </svg>
        </a>
      </div>
    </div>
    
    <div class="footer-column">
      <h4>Platform</h4>
      <div class="footer-links">
        <a href="#home">Dashboard</a>
        <a href="#about">About</a>
        <a href="#analytics">Analytics</a>
      
      </div>
    </div>
    
    <div class="footer-column">
      <h4>Solutions</h4>
      <div class="footer-links">
        <a href="#">XGBoost Models</a>
        <a href="#">Real-time Analytics</a>
        <a href="#">API Integration</a>
        <a href="#">Custom Reports</a>
      </div>
    </div>
   
  </div>
  
  <div class="footer-bottom">
    <div class="footer-bottom-content">
      <div class="copyright">
        © 2024 <a href="#">ChurnGuard Analytics</a>. All Rights Reserved.
      </div>
      <div class="legal-links">
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
        <a href="#">Data Security</a>
      </div>
    </div>
  </div>
</footer>


  
  <script>
    // Mobile menu toggle
    const toggleMenu = () => {
      const menu = document.querySelector('nav ul');
      const toggle = document.querySelector('.menu-toggle');
      menu.classList.toggle('active');
      toggle.classList.toggle('active');
    };
    document.querySelector('.menu-toggle').addEventListener('click', toggleMenu);

    // Header Scroll Effect
    window.addEventListener('scroll', function() {
      const header = document.querySelector('header');
      header.classList.toggle('scrolled', window.scrollY > 50);
    });

    // Smooth Scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
          behavior: 'smooth'
        });
        const menu = document.querySelector('nav ul');
        const toggle = document.querySelector('.menu-toggle');
        menu.classList.remove('active');
        toggle.classList.remove('active');
      });
    });
  </script>
  
  
  
  
  
  
  <script>
(function () {
  const form = document.getElementById('contact-form');
  const statusEl = document.getElementById('form-status');
  const mailtoA = document.getElementById('mailtoLink');
  const TO = 'ysl.aether.bank@gmail.com';

  function setStatus(msg, ok) {
    if (!statusEl) return;
    statusEl.textContent = msg;
    statusEl.style.color = ok ? '#1b7e1b' : '#cc0000';
  }
  function line(s) { return String(s || '').replace(/[\r\n]+/g, ' ').trim(); }

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    // simple honeypot
    if (line(form.website && form.website.value)) {
      setStatus('Thanks. Message queued.', true);
      return;
    }

    const name = line(form.name.value);
    const fromEmail = line(form.email.value);
    const company = line(form.company.value);
    const message = String(form.message.value || '').trim();

    if (!name || !fromEmail || !message) {
      setStatus('Please fill in your name, email, and message.', false);
      return;
    }
    const okEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fromEmail);
    if (!okEmail) {
      setStatus('Please enter a valid email address.', false);
      return;
    }

    const subject = `[Website Contact] ${name} - ${company || 'No company'}`;
    const bodyLines = [
      'New contact form submission',
      '',
      `Name: ${name}`,
      `Email: ${fromEmail}`,
      `Company: ${company || '—'}`,
      '',
      'Message:',
      message
    ];
    // Build mailto
    const mailto = `mailto:${encodeURIComponent(TO)}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(bodyLines.join('\n'))}`;

    // Open compose window
    try {
      window.location.href = mailto;
      setStatus('Opening your email app. Review then press Send.', true);
      // Show fallback link in case handlers are blocked
      mailtoA.href = mailto;
      mailtoA.style.display = 'inline-block';
      mailtoA.textContent = 'If nothing opened, click here to compose the email';
    } catch (err) {
      console.error(err);
      setStatus('Could not open your email app. Click the link below.', false);
      mailtoA.href = mailto;
      mailtoA.style.display = 'inline-block';
      mailtoA.textContent = 'Click here to compose the email';
    }
  });
})();
</script>
  
  
  
</body>

</html>