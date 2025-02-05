import Hero from "@/components/landing/Hero";
import Image from "next/image";
import Panoramic from "@/components/landing/Panoramic";
import Cards from "@/components/landing/Cards";
import Features from "@/components/landing/Features";
import Footer from "@/components/landing/Footer";

export default function Home() {
  return (
    <div className="min-h-screen font-[family-name:var(--font-geist-sans)] dark">
      <main className="flex flex-col gap-8">
        <Hero />

        <div className="mx-auto max-w-7xl flex flex-col gap-12">
          <Cards />
          <Panoramic />
          <div className="rounded-tr-xl rounded-tl-xl shadow-[0_0_20px_rgba(255,255,255,0.5)]">
            <Image
              src="/home2.png"
              alt="Hero"
              layout="responsive"
              width={1920}
              height={1080}
              className="inset-0 object-cover rounded-tr-xl rounded-tl-xl"
            />
          </div>
          <Features />
        </div>

        <Footer />
      </main>
    </div>
  );
}
