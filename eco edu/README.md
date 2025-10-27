# 🌱 EcoEdu - Gamified Environmental Education Platform

A comprehensive full-stack web application that makes environmental education fun, interactive, and rewarding through gamification.

## 🎯 Features

### 🔐 Authentication & User Management
- **User Registration & Login** - Secure authentication system
- **Role-based Access** - Student, Teacher, and Admin roles
- **Profile Management** - Customizable user profiles
- **Password Reset** - Secure password recovery

### 🎮 Gamification System
- **Eco-Points** - Earn points for completing activities
- **Badges & Achievements** - Unlock badges for milestones
- **Level System** - Progress through environmental expertise levels
- **Leaderboards** - Global, school, and class rankings

### 📚 Learning Content
- **Interactive Lessons** - Rich multimedia environmental content
- **Categorized Topics** - Climate change, renewable energy, waste management, etc.
- **Difficulty Levels** - Beginner to advanced content
- **Progress Tracking** - Monitor learning journey

### 🧠 Quiz System
- **Multiple Question Types** - Multiple choice, true/false, fill-in-the-blank
- **Timed Quizzes** - Configurable time limits
- **Instant Feedback** - Immediate results and explanations
- **Retry Mechanism** - Improve scores with multiple attempts

### 🏆 Challenge System
- **Real-world Tasks** - Plant trees, reduce waste, energy conservation
- **Photo/Video Verification** - Submit proof of completion
- **Individual & Group Challenges** - Solo or collaborative activities
- **Admin Verification** - Teacher/admin approval system

### 📊 Analytics & Reports
- **Progress Dashboard** - Personal achievement overview
- **School Comparisons** - Inter-school competition stats
- **Activity Reports** - Detailed participation analytics

### 🎨 Modern UI/UX
- **Responsive Design** - Mobile-first approach with desktop optimization
- **Dark/Light Theme** - User preference theme switching
- **Smooth Animations** - Engaging micro-interactions
- **Material Design** - Modern, clean interface

## 🛠️ Technology Stack

### Frontend
- **HTML5** - Semantic markup
- **CSS3** - Modern styling with custom properties
- **JavaScript (ES6+)** - Interactive functionality
- **MDBootstrap** - UI component framework
- **Font Awesome** - Icon library

### Backend
- **PHP 8+** - Server-side logic
- **MySQL** - Relational database
- **PDO** - Database abstraction layer
- **Session Management** - Secure user sessions

### Development Environment
- **XAMPP** - Local development server
- **Apache** - Web server
- **phpMyAdmin** - Database management

## 🚀 Installation & Setup

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- Modern web browser
- Text editor/IDE

### Step 1: Download & Extract
1. Download the project files
2. Extract to `C:\xampp\htdocs\eco edu\`

### Step 2: Database Setup
1. Start XAMPP (Apache + MySQL)
2. Open phpMyAdmin (http://localhost/phpmyadmin)
3. Import the database schema:
   ```sql
   -- Run the contents of database/schema.sql
   ```

### Step 3: Configuration
1. Update database credentials in `config/database.php` if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'ecoedu_db');
   ```

### Step 4: Access the Application
1. Open browser and navigate to: `http://localhost/eco edu/`
2. Register a new account or use demo credentials:
   - **Student**: `student@demo.com` / `demo123`
   - **Teacher**: `teacher@demo.com` / `demo123`

## 📁 Project Structure

```
eco edu/
├── api/                    # API endpoints
│   └── join_challenge.php  # Challenge participation API
├── assets/                 # Static assets
│   ├── css/
│   │   └── style.css      # Custom styles
│   └── js/
│       └── main.js        # JavaScript functionality
├── config/                 # Configuration files
│   └── database.php       # Database connection
├── database/              # Database files
│   └── schema.sql         # Database schema
├── includes/              # PHP includes
│   └── functions.php      # Core functions
├── admin/                 # Admin panel (future)
├── uploads/               # File uploads (future)
├── index.php             # Landing page
├── login.php             # Login page
├── register.php          # Registration page
├── dashboard.php         # User dashboard
├── quizzes.php           # Quiz listing
├── quiz.php              # Quiz taking interface
├── challenges.php        # Challenge listing
├── leaderboard.php       # Rankings and leaderboards
├── logout.php            # Logout handler
└── README.md             # This file
```

## 🎮 How to Use

### For Students
1. **Register** - Create your account with school details
2. **Explore** - Browse learning content and quizzes
3. **Learn** - Complete lessons to earn eco-points
4. **Quiz** - Test knowledge with interactive quizzes
5. **Challenge** - Join real-world environmental challenges
6. **Compete** - Check leaderboards and compare with peers

### For Teachers/Admins
1. **Monitor** - Track student progress and engagement
2. **Create** - Add new quizzes and challenges (admin panel)
3. **Verify** - Approve challenge submissions
4. **Analyze** - Review school and class performance

## 🌟 Key Features Explained

### Gamification Elements
- **Points System**: Earn 10-50 points per activity
- **Badges**: 8+ achievement badges (First Steps, Quiz Master, etc.)
- **Levels**: 7 progression levels (Eco Newbie to Eco Legend)
- **Streaks**: Daily login and activity streaks

### Quiz System
- **Adaptive Difficulty**: Easy, Medium, Hard levels
- **Time Management**: Configurable time limits
- **Multiple Attempts**: Improve scores with retakes
- **Detailed Feedback**: Explanations for correct answers

### Challenge Types
- **Individual**: Personal eco-friendly tasks
- **Group**: Collaborative environmental projects
- **School**: Institution-wide initiatives
- **Verification**: Photo, video, or text proof required

## 🔧 Customization

### Adding New Content
1. **Categories**: Add new environmental topics in database
2. **Quizzes**: Create questions through admin interface
3. **Challenges**: Design real-world activities
4. **Badges**: Define new achievement criteria

### Theming
- Modify CSS variables in `assets/css/style.css`
- Update color schemes and branding
- Customize animations and transitions

### Configuration
- Adjust point values in `includes/functions.php`
- Modify level requirements in database
- Update badge criteria and rewards

## 🚀 Deployment Options

### Local Hosting (XAMPP)
- Perfect for development and testing
- Easy setup and configuration
- Full control over environment

### Shared Hosting
- **InfinityFree**: Free hosting option
- **Hostinger**: Affordable premium hosting
- **000webhost**: Free tier available

### Cloud Hosting
- **DigitalOcean**: Scalable VPS hosting
- **AWS**: Enterprise-grade infrastructure
- **Google Cloud**: Reliable cloud platform

## 🔒 Security Features

- **Password Hashing**: Secure bcrypt encryption
- **SQL Injection Protection**: PDO prepared statements
- **XSS Prevention**: Input sanitization
- **CSRF Protection**: Token-based form security
- **Session Management**: Secure session handling

## 📱 Mobile Responsiveness

- **Mobile-First Design**: Optimized for smartphones
- **Touch-Friendly**: Large buttons and intuitive navigation
- **Responsive Grid**: Adapts to all screen sizes
- **Fast Loading**: Optimized assets and code

## 🎯 Future Enhancements

### Phase 2 Features
- **Mobile App**: React Native or Flutter app
- **AI Integration**: Personalized learning recommendations
- **Social Features**: Student forums and discussions
- **Advanced Analytics**: Detailed progress insights

### Phase 3 Features
- **QR Code Challenges**: Location-based activities
- **Video Lessons**: Multimedia learning content
- **Certificates**: Downloadable achievement certificates
- **API Integration**: Third-party environmental data

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit changes (`git commit -m 'Add AmazingFeature'`)
4. Push to branch (`git push origin feature/AmazingFeature`)
5. Open Pull Request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

For support and questions:
- Check the documentation
- Review common issues in the code comments
- Ensure XAMPP is running properly
- Verify database connection settings

## 🌍 Environmental Impact

EcoEdu aims to:
- **Educate** the next generation about environmental issues
- **Inspire** real-world eco-friendly actions
- **Gamify** learning to increase engagement
- **Connect** students globally for environmental causes

---

**Made with 💚 for a sustainable future**

Start your eco-journey today with EcoEdu! 🌱
