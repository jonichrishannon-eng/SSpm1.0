// 3D Atomic Quarks with Three.js for Home Page
let scene, camera, renderer, molecule;
let mouseX = 0, mouseY = 0;
let windowHalfX = window.innerWidth / 2;
let windowHalfY = window.innerHeight / 2;

const isHomePage = window.location.pathname.endsWith('Home.html') || window.location.pathname.endsWith('/');

if (isHomePage) {
    init();
    animate();
}

function init() {
    const container = document.createElement('div');
    container.id = 'atoms-container';
    container.style.position = 'fixed';
    container.style.top = '0';
    container.style.left = '0';
    container.style.width = '100%';
    container.style.height = '100%';
    container.style.zIndex = '0'; // Above particles background, below content
    container.style.pointerEvents = 'none';
    document.body.appendChild(container);

    scene = new THREE.Scene();
    camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    camera.position.z = 5;

    renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setPixelRatio(window.devicePixelRatio);
    container.appendChild(renderer.domElement);

    molecule = new THREE.Group();
    scene.add(molecule);

    // Create Atoms/Molecules consisting of Quarks
    const quarkGeometry = new THREE.SphereGeometry(0.1, 32, 32);
    const colors = [0xff0000, 0x00ff00, 0x0000ff]; // Red, Green, Blue

    for (let i = 0; i < 100; i++) {
        const atomGroup = new THREE.Group();
        
        // 3 Quarks per atom
        for (let j = 0; j < 3; j++) {
            const material = new THREE.MeshPhongMaterial({ 
                color: colors[j],
                emissive: colors[j],
                emissiveIntensity: 0.5,
                shininess: 100
            });
            const quark = new THREE.Mesh(quarkGeometry, material);
            
            // Position quarks in a triangle/spinning formation
            const angle = (j / 3) * Math.PI * 2;
            quark.position.set(Math.cos(angle) * 0.2, Math.sin(angle) * 0.2, 0);
            atomGroup.add(quark);
        }

        // Random position for each "atom"
        atomGroup.position.x = (Math.random() - 0.5) * 10;
        atomGroup.position.y = (Math.random() - 0.5) * 10;
        atomGroup.position.z = (Math.random() - 0.5) * 10;
        
        molecule.add(atomGroup);
    }

    // Add lines between some atoms (red glowing gradient)
    const lineMaterial = new THREE.LineBasicMaterial({ 
        color: 0xff0000, 
        transparent: true, 
        opacity: 0.2 
    });
    const atoms = molecule.children;
    for (let i = 0; i < atoms.length; i++) {
        if (Math.random() > 0.8) {
            const nextIndex = (i + 1) % atoms.length;
            const points = [];
            points.push(atoms[i].position);
            points.push(atoms[nextIndex].position);
            const geometry = new THREE.BufferGeometry().setFromPoints(points);
            const line = new THREE.Line(geometry, lineMaterial);
            molecule.add(line);
        }
    }

    // Lights
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
    scene.add(ambientLight);
    const pointLight = new THREE.PointLight(0xffffff, 1);
    pointLight.position.set(5, 5, 5);
    scene.add(pointLight);

    document.addEventListener('mousemove', onDocumentMouseMove);
    window.addEventListener('resize', onWindowResize);
    
    // Zoom out effect on menu toggle
    const menuIcon = document.getElementById('menu-icon');
    if (menuIcon) {
        menuIcon.addEventListener('change', function() {
            if (this.checked) {
                // Zoom out
                gsap.to(camera.position, { z: 20, duration: 1.5, ease: "power2.inOut" });
                gsap.to(molecule.rotation, { y: Math.PI * 2, duration: 2, ease: "power2.inOut" });
            } else {
                // Zoom in
                gsap.to(camera.position, { z: 5, duration: 1.5, ease: "power2.inOut" });
            }
        });
    }
}

function onDocumentMouseMove(event) {
    mouseX = (event.clientX - windowHalfX) / 100;
    mouseY = (event.clientY - windowHalfY) / 100;
}

function onWindowResize() {
    windowHalfX = window.innerWidth / 2;
    windowHalfY = window.innerHeight / 2;
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
}

function animate() {
    requestAnimationFrame(animate);
    
    molecule.rotation.x += 0.001;
    molecule.rotation.y += 0.002;

    // Spin quarks within atoms
    molecule.children.forEach(child => {
        if (child instanceof THREE.Group) {
            child.rotation.z += 0.05;
        }
    });

    // Gentle camera movement
    camera.position.x += (mouseX - camera.position.x) * 0.05;
    camera.position.y += (-mouseY - camera.position.y) * 0.05;
    camera.lookAt(scene.position);

    renderer.render(scene, camera);
}
