# 🚗 Elite Motors - Car Dealership Management System

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A comprehensive web-based car dealership management system that connects car buyers and sellers with an intuitive interface and powerful admin panel.

## ✨ Features

- **For Buyers**
  - Browse cars with advanced filters
  - View detailed car specifications
  - Contact sellers directly
  - Save favorite listings

- **For Sellers**
  - Easy car listing management
  - Image upload functionality
  - Track inquiries and messages

- **Admin Panel**
  - Manage inventory
  - Handle user accounts
  - Monitor website activity
  - Generate reports

## 🛠️ Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Server**: Apache (XAMPP)
- **Version Control**: Git

## 🚀 Installation Guide

### Prerequisites
- XAMPP/WAMP server
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web browser (Chrome, Firefox, etc.)

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/elite-motors.git
   ```

2. **Database Setup**
   - Create a new MySQL database
   - Import the database schema from `database/elite_motors.sql`
   - Update database credentials in `database/config.php`

3. **Configure Application**
   - Set up your web server (Apache) to point to the project directory
   - Ensure the `uploads/` directory is writable

4. **Access the Application**
   - Open your browser and navigate to `http://localhost/EliteMotors`
   - Admin panel: `http://localhost/EliteMotors/admin`
     - Default admin credentials:
       - Email: admin@elite-motors.com
       - Password: Admin@123

## 📂 Project Structure

```
EliteMotors/
├── admin/               # Admin panel files
├── assets/              # Static assets (CSS, JS, images)
│   ├── css/            # Stylesheets
│   ├── js/             # JavaScript files
│   └── images/         # Static images
├── database/           # Database schema and config
├── includes/           # PHP includes and functions
├── uploads/            # User-uploaded content
├── .gitignore         # Git ignore file
└── README.md          # This file
```

## 🔐 Security

- Password hashing using PHP's `password_hash()`
- Prepared statements to prevent SQL injection
- Input validation and sanitization
- CSRF protection
- Secure file upload validation

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🤝 Contributing

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📧 Contact

For any queries or support, please contact:
- Email: your.email@example.com
- GitHub: [@EliteMotors](https://github.com/dharmikghaskata/EliteMotors)

## 🙏 Acknowledgments

- [Bootstrap](https://getbootstrap.com/) for the responsive design
- [Font Awesome](https://fontawesome.com/) for icons
- All contributors who helped in the development

---

<div align="center">
  Made with ❤️ by Dharmik Ghaskata
</div>
