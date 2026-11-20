<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <title>Douane</title>
    <link href="https://fonts.googleapis.com/css?family=Orbitron:400,500,700,900" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-latest.min.js" type="text/javascript"></script>

    <link rel="stylesheet" href="css/all.css">
    <link rel="stylesheet" href="css/style.css">
    <script>
        (function () {
            try {
                if (localStorage.getItem('douane_a11y') === '1') {
                    var root = document.documentElement;
                    root.classList.add('a11y-enabled');
                    if (localStorage.getItem('douane_theme') === 'light') {
                        root.classList.add('theme-light');
                    }
                }
            } catch (e) {}
        })();
    </script>

    <!--[if lt IE 9]>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.3/html5shiv.js"></script>
    <![endif]-->
</head>
<body>
<div id="moving-background"></div>
<div class="background">
    <img class="icc-logo" src="images/logos/icc.png" />
    <header>
        <div class="container">
            <h1>
                <a href="./">
                    Douane Control Panel
                </a>
            </h1>
            <div class="header-bar">
                <nav>
                    <ul>
                        <li>
                            <a href="checkinout.php">
                                <i class="fas fa-sign-in-alt"></i> Check In/Out
                            </a>
                        </li>
                        <li>
                            <a href="checkbastion.php">
                                <i class="fas fa-clipboard-check"></i> Check Bastion
                            </a>
                        </li>
                        <li>
                            <a href="check.php">
                                <i class="fas fa-user-check"></i> Check Person
                            </a>
                        </li>
                    </ul>
                </nav>
                <div class="a11y-controls" role="region" aria-label="Accessibility settings">
                    <button type="button"
                            id="a11y-toggle"
                            class="a11y-toggle-btn"
                            aria-pressed="false"
                            aria-controls="a11y-theme-group"
                            title="Toggle accessibility mode (disables animated background and reveals light/dark theme)">
                        <i class="fas fa-universal-access" aria-hidden="true"></i>
                        <span>Accessibility</span>
                        <span class="a11y-toggle-state" aria-hidden="true">OFF</span>
                    </button>
                    <div id="a11y-theme-group" class="a11y-theme-group" role="group" aria-label="Color theme" hidden>
                        <button type="button"
                                class="a11y-theme-btn is-selected"
                                data-theme-choice="dark"
                                aria-pressed="true"
                                title="Use dark mode">
                            <i class="fas fa-moon" aria-hidden="true"></i>
                            <span>Dark</span>
                        </button>
                        <button type="button"
                                class="a11y-theme-btn"
                                data-theme-choice="light"
                                aria-pressed="false"
                                title="Use light mode">
                            <i class="fas fa-sun" aria-hidden="true"></i>
                            <span>Light</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>
